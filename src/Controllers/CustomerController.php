<?php

declare(strict_types=1);

namespace ScalE\Controllers;

use ScalE\Http\Request;
use ScalE\Services\CustomerService;

final class CustomerController extends BaseController
{
    private CustomerService $customerService;

    public function __construct(
        ?CustomerService $customerService = null
    ) {
        $this->customerService = $customerService ?? new CustomerService();
    }

    public function show(Request $request): \ScalE\Http\Response
    {
        $id = (int) preg_replace('/[^0-9]/', '', $request->path());
        if ($id <= 0) {
            return $this->jsonResponse(['error' => 'Customer id is required.'], 400);
        }

        try {
            return $this->jsonResponse($this->customerService->getCustomerProfile($id));
        } catch (\Throwable $throwable) {
            return $this->jsonResponse(['error' => $throwable->getMessage()], 404);
        }
    }

    public function index(Request $request): \ScalE\Http\Response
    {
        $query = $request->query();
        $page = filter_var($query['page'] ?? 1, FILTER_VALIDATE_INT);
        $perPage = filter_var($query['per_page'] ?? 5, FILTER_VALIDATE_INT);
        $orderBy = $query['order_by'] ?? 'created_at';
        $order = $query['order'] ?? 'desc';

        if ($page === false || $page < 1 || $perPage === false || $perPage < 1 || $perPage > 50
            || !is_string($orderBy)
            || !in_array($orderBy, ['name', 'email', 'created_at', 'updated_at'], true)
            || !is_string($order)
            || !in_array(strtolower($order), ['asc', 'desc'], true)
        ) {
            return $this->jsonResponse([
                'error' => 'Invalid pagination or customer sort parameters.',
            ], 400);
        }

        $order = strtolower($order);
        return $this->jsonResponse($this->customerService->listCustomers($page, $perPage, $orderBy, $order));
    }
}
