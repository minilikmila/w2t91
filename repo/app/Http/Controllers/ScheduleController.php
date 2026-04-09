<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    use AuthorizesRecordAccess;

    public function index(Request $request): JsonResponse
    {
        $query = Schedule::with('resource');

        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->has('date')) {
            $query->whereDate('date', $request->date);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('date', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'slot_duration_minutes' => 'nullable|integer|min:1',
            'capacity_per_slot' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $data = $request->only([
            'resource_id', 'date', 'start_time', 'end_time',
            'slot_duration_minutes', 'capacity_per_slot', 'is_active', 'metadata',
        ]);

        if (!isset($data['slot_duration_minutes'])) {
            $data['slot_duration_minutes'] = 15;
        }

        $schedule = Schedule::create($data);

        return response()->json([
            'message' => 'Schedule created successfully.',
            'data' => $schedule,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::with('resource')->findOrFail($id);
        $this->authorizeRecord($request, $schedule);

        return response()->json(['data' => $schedule]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'resource_id' => 'sometimes|required|exists:resources,id',
            'date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',
            'slot_duration_minutes' => 'nullable|integer|min:1',
            'capacity_per_slot' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $schedule = Schedule::findOrFail($id);
        $this->authorizeMutation($request, $schedule);
        $schedule->update($request->only([
            'resource_id', 'date', 'start_time', 'end_time',
            'slot_duration_minutes', 'capacity_per_slot', 'is_active', 'metadata',
        ]));

        return response()->json([
            'message' => 'Schedule updated successfully.',
            'data' => $schedule->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorizeMutation($request, $schedule);
        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully.']);
    }

    public function slots(Request $request, int $id): JsonResponse
    {
        $schedule = Schedule::findOrFail($id);
        $this->authorizeRecord($request, $schedule);

        return response()->json([
            'data' => $schedule->getSlots(),
        ]);
    }
}
