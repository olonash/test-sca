<?php

declare(strict_types=1);

namespace ScalE\Services;

use ScalE\Repositories\EventRepository;

final class EventService
{
    private EventRepository $eventRepository;

    public function __construct(
        ?EventRepository $eventRepository = null
    ) {
        $this->eventRepository = $eventRepository ?? new EventRepository();
    }

    public function ingest(array $payload): array
    {
        $customerId = (int) $payload['customer_id'];

        $event = $this->eventRepository->create(
            $customerId,
            $payload['event'],
            $payload['timestamp'],
            $payload['properties']
        );

        return [
            'message' => 'Event ingested successfully.',
            'event' => $event,
        ];
    }
}
