<?php

declare(strict_types=1);

namespace ScalE\Repositories;

use ScalE\Config\Database;

final class SegmentRepository
{
    public function findCustomersMatching(array $conditions): array
    {
        $pdo = Database::getConnection();
        $sqlParts = [];
        $params = [];

        //var_dump($conditions);
        foreach ($conditions as $index => $condition) {
            $eventName = $condition['event'];
            $property = $condition['property'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            $sqlParts[] = sprintf(
                'EXISTS (
                    SELECT 1
                    FROM events e
                    INNER JOIN event_properties ep ON ep.event_id = e.id
                                        INNER JOIN event_types et ON et.id = e.event_type_id
                    WHERE e.customer_id = c.id
                                            AND et.code = :event_%1$s
                      AND ep.property_name = :property_%1$s
                      AND ep.value_decimal %2$s :value_%1$s
                )',
                $index,
                $this->normalizeOperator($operator)
            );

            $params['event_' . $index] = $eventName;
            $params['property_' . $index] = $property;
            $params['value_' . $index] = (float) $value;
        }
        //var_dump($sqlParts); die;
        $sql = 'SELECT DISTINCT c.id, c.email, c.name FROM customers c WHERE ' . implode(' AND ', $sqlParts);
        //var_dump($sql);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function normalizeOperator(string $operator): string
    {
        return match ($operator) {
            '>' => '>',
            '>=' => '>=',
            '<' => '<',
            '<=' => '<=',
            '=' => '=',
            '==' => '=',
            '!=' => '!=',
            default => throw new \InvalidArgumentException(sprintf('Unsupported operator: %s', $operator)),
        };
    }
}
