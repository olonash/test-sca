<?php

declare(strict_types=1);

namespace ScalE\Repositories;

use PDO;
use ScalE\Config\Database;

final class EventRepository
{
    public function create(int $customerId, string $eventName, string $timestamp, array $properties): array
    {
        $pdo = Database::getConnection();
        $pdo->beginTransaction();

        try {
            $normalizedTimestamp = $this->normalizeTimestamp($timestamp);
            $eventType = $pdo->prepare(
                'INSERT INTO event_types (code, label) VALUES (:code, :label) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
            );
            $eventType->execute(['code' => $eventName, 'label' => $eventName]);
            $eventTypeId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                'INSERT INTO events (customer_id, event_type_id, event_timestamp, created_at) VALUES (:customer_id, :event_type_id, :event_timestamp, NOW())'
            );
            $stmt->execute([
                'customer_id' => $customerId,
                'event_type_id' => $eventTypeId,
                'event_timestamp' => $normalizedTimestamp,
            ]);

            $eventId = (int) $pdo->lastInsertId();
            $this->storeProperties($pdo, $eventId, $properties);
            $pdo->commit();

            return $this->findById($eventId);
        } catch (\Throwable $throwable) {
            $pdo->rollBack();
            throw $throwable;
        }
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT e.*, et.code AS event_name, et.label AS event_type_label
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             WHERE e.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public function findLastByCustomer(int $customerId, int $limit = 10): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT e.*, et.code AS event_name, et.label AS event_type_label
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             WHERE e.customer_id = :customer_id
             ORDER BY e.event_timestamp DESC LIMIT :limit'
        );
        $stmt->bindValue('customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countByCustomerAndEvent(int $customerId, string $eventName): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM events e
             INNER JOIN event_types et ON et.id = e.event_type_id
             WHERE e.customer_id = :customer_id AND et.code = :event_code'
        );
        $stmt->execute(['customer_id' => $customerId, 'event_code' => $eventName]);

        return (int) $stmt->fetch()['total'];
    }

    public function sumPropertyByCustomerAndEvent(int $customerId, string $eventName, string $propertyName): float
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare(
            'SELECT SUM(value_decimal) AS total FROM event_properties WHERE event_id IN (
                SELECT e.id
                FROM events e
                INNER JOIN event_types et ON et.id = e.event_type_id
                WHERE e.customer_id = :customer_id AND et.code = :event_code
            ) AND property_name = :property_name'
        );
        $stmt->execute([
            'customer_id' => $customerId,
            'event_code' => $eventName,
            'property_name' => $propertyName,
        ]);

        $result = $stmt->fetch();
        return (float) ($result['total'] ?? 0);
    }

    public function findCustomerIdsBySegment(array $conditions): array
    {
        $pdo = Database::getConnection();

        $whereConditions = [];
        $params = [];
        foreach ($conditions as $index => $condition) {
            $alias = 'c' . $index;
            $whereConditions[] = sprintf(
                'EXISTS (
                    SELECT 1
                    FROM event_properties ep
                    INNER JOIN events e ON e.id = ep.event_id
                    INNER JOIN event_types et ON et.id = e.event_type_id
                    WHERE e.customer_id = c.id
                    AND et.code = :event_%1$s
                    AND ep.property_name = :property_%1$s
                    AND ep.value_decimal %2$s :value_%1$s
                )',
                $index,
                $condition['operator']
            );
            $params['event_' . $index] = $condition['event'];
            $params['property_' . $index] = $condition['property'];
            $params['value_' . $index] = (float) $condition['value'];
        }

        $sql = sprintf(
            'SELECT DISTINCT c.id FROM customers c WHERE %s',
            implode(' AND ', $whereConditions)
        );

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return array_map(static fn (array $row): int => (int) $row['id'], $stmt->fetchAll());
    }

    private function normalizeTimestamp(string $timestamp): string
    {
        $dateTime = new \DateTimeImmutable($timestamp);
        return $dateTime->format('Y-m-d H:i:s');
    }

    private function storeProperties(PDO $pdo, int $eventId, array $properties): void
    {
        if ($properties === []) {
            return;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO event_properties (event_id, property_name, property_type, value_string, value_int, value_decimal, value_bool, value_json) VALUES (:event_id, :property_name, :property_type, :value_string, :value_int, :value_decimal, :value_bool, :value_json)'
        );

        foreach ($properties as $name => $value) {
            $propertyType = $this->detectType($value);
            $payload = [
                'event_id' => $eventId,
                'property_name' => (string) $name,
                'property_type' => $propertyType,
                'value_string' => is_string($value) ? $value : null,
                'value_int' => is_int($value) ? $value : null,
                'value_decimal' => is_float($value) || is_int($value) ? (float) $value : null,
                'value_bool' => is_bool($value) ? (int) $value : null,
                'value_json' => is_array($value) || is_object($value) ? json_encode($value) : null,
            ];

            $stmt->execute($payload);
        }
    }

    private function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }

        if (is_int($value)) {
            return 'int';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value) || is_object($value)) {
            return 'json';
        }

        return 'string';
    }
}
