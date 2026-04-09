<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Resources\FieldPlacementResource;
use App\Models\FieldPlacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FieldPlacementController extends Controller
{
    use AuthorizesRecordAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = FieldPlacement::with(['learner', 'location', 'assignedByUser']);

        // Scope results based on user role
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('planner')) {
            $query->where('assigned_by', $user->id);
        }

        if ($request->has('learner_id')) {
            $query->where('learner_id', $request->learner_id);
        }

        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return FieldPlacementResource::collection($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'learner_id' => 'required|exists:learners,id',
            'location_id' => 'required|exists:locations,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
            'metadata' => 'nullable|array',
        ]);

        $placement = FieldPlacement::create([
            'learner_id' => $request->learner_id,
            'location_id' => $request->location_id,
            'assigned_by' => $request->user()->id,
            'status' => FieldPlacement::STATUS_PENDING,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'notes' => $request->notes,
            'metadata' => $request->metadata,
        ]);

        $placement->load(['learner', 'location', 'assignedByUser']);

        return response()->json([
            'message' => 'Field placement created.',
            'data' => new FieldPlacementResource($placement),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $placement = FieldPlacement::with(['learner', 'location', 'assignedByUser'])->findOrFail($id);
        $this->authorizeRecord($request, $placement);

        return response()->json(['data' => new FieldPlacementResource($placement)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|string|in:pending,active,completed,cancelled',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'notes' => 'nullable|string|max:2000',
        ]);

        $placement = FieldPlacement::findOrFail($id);
        $this->authorizeMutation($request, $placement);

        $placement->update($request->only(['status', 'start_date', 'end_date', 'notes']));

        $placement->load(['learner', 'location', 'assignedByUser']);

        return response()->json([
            'message' => 'Field placement updated.',
            'data' => new FieldPlacementResource($placement),
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $placement = FieldPlacement::findOrFail($id);
        $this->authorizeMutation($request, $placement);

        if ($placement->status === FieldPlacement::STATUS_CANCELLED) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => 'This placement is already cancelled.',
            ], 422);
        }

        $placement->update([
            'status' => FieldPlacement::STATUS_CANCELLED,
            'notes' => $request->notes ?? $placement->notes,
        ]);

        $placement->load(['learner', 'location', 'assignedByUser']);

        return response()->json([
            'message' => 'Field placement cancelled.',
            'data' => new FieldPlacementResource($placement),
        ]);
    }
}
