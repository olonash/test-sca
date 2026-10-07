<?php

declare(strict_types=1);

namespace ScalE\Services;

use ScalE\Repositories\SegmentRepository;

final class SegmentationService
{
    private SegmentRepository $segmentRepository;

    public function __construct(
        ?SegmentRepository $segmentRepository = null
    ) {
        $this->segmentRepository = $segmentRepository ?? new SegmentRepository();
    }

    public function query(array $conditions): array
    {
        return $this->segmentRepository->findCustomersMatching($conditions);
    }
}
