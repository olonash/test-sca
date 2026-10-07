<?php

declare(strict_types=1);

namespace ScalE\Repositories;

use PDO;
use ScalE\Config\Database;

final class CustomerRepository
{
    private const SORTABLE_COLUMNS = [
        'name' => 'name',
        'email' => 'email',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);

        $customer = $stmt->fetch();
        return $customer ?: null;
    }

    public function findById(int $id): ?array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);

        $customer = $stmt->fetch();
        return $customer ?: null;
    }

    public function upsert(string $email, string $name): array
    {
        $pdo = Database::getConnection();
        $existing = $this->findByEmail($email);

        if ($existing !== null) {
            $stmt = $pdo->prepare('UPDATE customers SET name = :name, updated_at = NOW() WHERE id = :id');
            $stmt->execute(['name' => $name, 'id' => $existing['id']]);
            return $this->findById((int) $existing['id']);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO customers (email, name, created_at, updated_at) VALUES (:email, :name, NOW(), NOW())'
        );
        $stmt->execute(['email' => $email, 'name' => $name]);

        return $this->findById((int) $pdo->lastInsertId());
    }

    public function listCustomers(
        int $limit = 20,
        int $offset = 0,
        string $orderBy = 'created_at',
        string $order = 'desc'
    ): array
    {
        $column = self::SORTABLE_COLUMNS[$orderBy] ?? null;
        $direction = strtolower($order);
        if ($column === null || !in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('Unsupported customer sort.');
        }

        $tieBreaker = $orderBy === 'email' ? '' : ', email ASC';
        $pdo = Database::getConnection();
        $sql = sprintf(
            'SELECT * FROM customers ORDER BY %s %s%s LIMIT :limit OFFSET :offset',
            $column,
            strtoupper($direction),
            $tieBreaker
        );
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function countCustomers(): int
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query('SELECT COUNT(*) FROM customers');

        return (int) $stmt->fetchColumn();
    }
}
