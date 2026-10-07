<?php

declare(strict_types=1);

namespace ScalE\Controllers;

use ScalE\Http\Request;
use ScalE\Services\CustomerService;
use ScalE\Services\EventService;
use ScalE\Validation\Validator;

final class EventController extends BaseController
{
    private CustomerService $customerService;
    private EventService $eventService;

    public function __construct(
        ?CustomerService $customerService = null,
        ?EventService $eventService = null
    ) {
        $this->customerService = $customerService ?? new CustomerService();
        $this->eventService = $eventService ?? new EventService();
    }

    public function ingest(Request $request): \ScalE\Http\Response
    {
        try {
            $payload = Validator::validateEventPayload($request->body());
            $customer = $this->customerService->upsertCustomer($payload['customer']);
            $event = $this->eventService->ingest([
                'customer_id' => $customer['id'],
                'event' => $payload['event'],
                'timestamp' => $payload['timestamp'],
                'properties' => $payload['properties'],
            ]);

            return $this->jsonResponse([
                'customer' => $customer,
                'event' => $event,
            ], 201);
        } catch (\Throwable $throwable) {
            return $this->jsonResponse(['error' => $throwable->getMessage()], 400);
        }
    }
}
