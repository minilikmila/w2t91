<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Requests\LearnerRequest;
use App\Http\Resources\LearnerResource;
use App\Models\Learner;
use App\Services\DeduplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class LearnerController extends Controller
{
    use AuthorizesRecordAccess;

    private DeduplicationService $deduplicationService;

    public function __construct(DeduplicationService $deduplicationService)
    {
        $this->deduplicationService = $deduplicationService;
    }
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Learner::with('identifiers');

        // Scope results for restricted roles: only show learners linked via bookings/enrollments
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('planner')) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('created_by', $userId)
                    ->orWhereHas('enrollments', function ($eq) use ($userId) {
                        $eq->where('last_actor_id', $userId);
                    })
                    ->orWhereHas('bookings', function ($bq) use ($userId) {
                        $bq->where('booked_by', $userId);
                    });
            });
        }

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
        $validated = $request->validated();

        // Check for duplicate candidates before creating
        $duplicateCandidates = $this->deduplicationService->findDuplicateCandidates($validated);

        // Wrap creation + deduplication in a transaction for data consistency
        $result = DB::transaction(function () use ($validated) {
            $learner = Learner::create($validated);
            $dedupResult = $this->deduplicationService->processLearner($learner);
            $learner->load('identifiers');

            return ['learner' => $learner, 'dedup' => $dedupResult];
        });

        $learner = $result['learner'];
        $dedupResult = $result['dedup'];

        $response = [
            'message' => 'Learner created successfully.',
            'data' => new LearnerResource($learner),
        ];

        if ($dedupResult['duplicate_count'] > 0 || !empty($duplicateCandidates)) {
            $response['duplicate_warning'] = [
                'message' => 'Potential duplicate learners detected.',
                'candidate_count' => max($dedupResult['duplicate_count'], count($duplicateCandidates)),
            ];
        }

        return response()->json($response, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $learner = Learner::with('identifiers')->findOrFail($id);
        $this->authorizeRecord($request, $learner);

        return response()->json([
            'data' => new LearnerResource($learner),
        ]);
    }

    public function update(LearnerRequest $request, int $id): JsonResponse
    {
        $learner = Learner::findOrFail($id);
        $this->authorizeMutation($request, $learner);

        $validated = $request->validated();

        // Wrap update + deduplication in a transaction
        DB::transaction(function () use ($learner, $validated) {
            $learner->update($validated);

            // Recompute fingerprint after identity fields change
            $this->deduplicationService->computeAndStoreFingerprint($learner);

            // Re-register identifier fingerprints when email/phone change
            if (isset($validated['email']) || isset($validated['phone'])) {
                if (isset($validated['email']) && $learner->email) {
                    $this->deduplicationService->registerIdentifier($learner, 'email', $learner->email, true);
                }
                if (isset($validated['phone']) && $learner->phone) {
                    $this->deduplicationService->registerIdentifier($learner, 'phone', $learner->phone);
                }
            }
        });

        $learner->load('identifiers');

        return response()->json([
            'message' => 'Learner updated successfully.',
            'data' => new LearnerResource($learner),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $learner = Learner::findOrFail($id);
        $this->authorizeMutation($request, $learner);
        $learner->delete();

        return response()->json([
            'message' => 'Learner deleted successfully.',
        ]);
    }
}
