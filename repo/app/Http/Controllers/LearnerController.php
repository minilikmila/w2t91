<?php

namespace App\Http\Controllers;

use App\Http\Requests\LearnerRequest;
use App\Http\Resources\LearnerResource;
use App\Models\Learner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LearnerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Learner::with('identifiers');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('search_email', 'like', "%{$search}%")
                    ->orWhere('search_phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('nationality')) {
            $query->where('nationality', $request->nationality);
        }

        if ($request->has('is_minor') && $request->is_minor === 'true') {
            $query->whereNotNull('date_of_birth')
                ->whereDate('date_of_birth', '>', now()->subYears(18));
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('direction', 'desc');
        $allowedSorts = ['first_name', 'last_name', 'email', 'created_at', 'updated_at', 'date_of_birth'];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return LearnerResource::collection($query->paginate($perPage));
    }

    public function store(LearnerRequest $request): JsonResponse
    {
        $learner = Learner::create($request->validated());
        $learner->load('identifiers');

        return response()->json([
            'message' => 'Learner created successfully.',
            'data' => new LearnerResource($learner),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $learner = Learner::with('identifiers')->findOrFail($id);

        return response()->json([
            'data' => new LearnerResource($learner),
        ]);
    }

    public function update(LearnerRequest $request, int $id): JsonResponse
    {
        $learner = Learner::findOrFail($id);
        $learner->update($request->validated());
        $learner->load('identifiers');

        return response()->json([
            'message' => 'Learner updated successfully.',
            'data' => new LearnerResource($learner),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $learner = Learner::findOrFail($id);
        $learner->delete();

        return response()->json([
            'message' => 'Learner deleted successfully.',
        ]);
    }
}
