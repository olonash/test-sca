<?php

declare(strict_types=1);

namespace ScalE\Validation;

final class Validator
{
    public static function validateEventPayload(array $payload): array
    {
        if (!isset($payload['customer'], $payload['event'], $payload['timestamp'])) {
            throw new \InvalidArgumentException('Payload invalid: customer, event and timestamp are required.');
        }

        if (!is_array($payload['customer'])) {
            throw new \InvalidArgumentException('Payload invalid: customer must be an object.');
        }

        $customer = $payload['customer'];
        if (empty($customer['email']) || !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Payload invalid: a valid customer email is required.');
        }

        if (empty($customer['name'])) {
            throw new \InvalidArgumentException('Payload invalid: customer name is required.');
        }

        $eventName = trim((string) $payload['event']);
        if (preg_match('/^.{1,20}$/us', $eventName) !== 1) {
            throw new \InvalidArgumentException('Payload invalid: event code must contain between 1 and 20 characters.');
        }

        if (!self::isValidTimestamp((string) $payload['timestamp'])) {
            throw new \InvalidArgumentException('Payload invalid: timestamp must be a valid ISO-8601 date.');
        }

        return [
            'customer' => [
                'email' => strtolower(trim((string) $customer['email'])),
                'name' => trim((string) $customer['name']),
            ],
            'event' => $eventName,
            'properties' => is_array($payload['properties'] ?? null) ? $payload['properties'] : [],
            'timestamp' => (string) $payload['timestamp'],
        ];
    }

    public static function validateSegmentQuery(array $payload): array
    {
        if (!isset($payload['conditions']) || !is_array($payload['conditions'])) {
            throw new \InvalidArgumentException('The conditions array is required.');
        }

        foreach ($payload['conditions'] as $index => $condition) {
            if (!is_array($condition)) {
                throw new \InvalidArgumentException(sprintf('Condition %d must be an object.', $index));
            }

            if (empty($condition['event']) || empty($condition['property']) || empty($condition['operator'])) {
                throw new \InvalidArgumentException(sprintf('Condition %d is incomplete.', $index));
            }
        }

        return $payload;
    }

    private static function isValidTimestamp(string $timestamp): bool
    {
        $date = date_create_immutable_from_format(DATE_ATOM, $timestamp);
        if ($date instanceof \DateTimeImmutable) {
            return true;
        }

        $date = date_create_immutable($timestamp);
        return $date !== false;
    }
}
