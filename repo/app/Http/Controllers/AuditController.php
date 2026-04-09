<?php

namespace App\Http\Controllers;

use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    private AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'event_type', 'entity_type', 'entity_id',
            'actor_id', 'from', 'to', 'per_page',
        ]);

        $events = $this->auditService->query($filters);

        return response()->json($events);
    }

    public function show(int $id): JsonResponse
    {
        $event = \App\Models\AuditEvent::with('actor')->findOrFail($id);

        return response()->json(['data' => $event]);
    }

    public function entityTrail(Request $request, string $entityType, int $entityId): JsonResponse
    {
        $filters = [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'per_page' => $request->get('per_page', 50),
        ];

        $events = $this->auditService->query($filters);

        return response()->json($events);
    }

    public function verifyChain(Request $request): JsonResponse
    {
        $limit = $request->has('limit') ? (int) $request->limit : null;

        $result = $this->auditService->verifyChain($limit);

        return response()->json($result);
    }
}
