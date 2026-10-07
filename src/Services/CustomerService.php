<?php

declare(strict_types=1);

namespace ScalE\Services;

use ScalE\Repositories\CustomerRepository;
use ScalE\Repositories\EventRepository;

final class CustomerService
{
    private CustomerRepository $customerRepository;
    private EventRepository $eventRepository;

    public function __construct(
        ?CustomerRepository $customerRepository = null,
        ?EventRepository $eventRepository = null
    ) {
        $this->customerRepository = $customerRepository ?? new CustomerRepository();
        $this->eventRepository = $eventRepository ?? new EventRepository();
    }

    public function upsertCustomer(array $payload): array
    {
        return $this->customerRepository->upsert($payload['email'], $payload['name']);
    }

    public function listCustomers(int $page, int $perPage, string $orderBy, string $order): array
    {
        $total = $this->customerRepository->countCustomers();
        $totalPages = (int) ceil($total / $perPage);
        $customers = $page > $totalPages
            ? []
            : $this->customerRepository->listCustomers($perPage, ($page - 1) * $perPage, $orderBy, $order);

        return [
            'customers' => $customers,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'order_by' => $orderBy,
                'order' => $order,
            ],
        ];
    }

    public function getCustomerProfile(int $id): array
    {
        $customer = $this->customerRepository->findById($id);
        if ($customer === null) {
            throw new \RuntimeException('Customer not found.');
        }

        $events = $this->eventRepository->findLastByCustomer($id, 10);
        $stats = [
            'total_events' => count($events),
            'purchase_count' => $this->eventRepository->countByCustomerAndEvent($id, 'purchase'),
            'total_spent' => $this->eventRepository->sumPropertyByCustomerAndEvent($id, 'purchase', 'amount'),
        ];

        return [
            'customer' => $customer,
            'events' => $events,
            'stats' => $stats,
        ];
    }
}
