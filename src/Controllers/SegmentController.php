<?php

declare(strict_types=1);

namespace ScalE\Controllers;

use ScalE\Http\Request;
use ScalE\Services\SegmentationService;
use ScalE\Validation\Validator;

final class SegmentController extends BaseController
{
    private SegmentationService $segmentationService;

    public function __construct(
        ?SegmentationService $segmentationService = null
    ) {
        $this->segmentationService = $segmentationService ?? new SegmentationService();
    }

    public function query(Request $request): \ScalE\Http\Response
    {
        try {
            $payload = Validator::validateSegmentQuery($request->body());
            $customers = $this->segmentationService->query($payload['conditions']);
            return $this->jsonResponse(['customers' => $customers]);
        } catch (\Throwable $throwable) {
            return $this->jsonResponse(['error' => $throwable->getMessage()], 400);
        }
    }
}
