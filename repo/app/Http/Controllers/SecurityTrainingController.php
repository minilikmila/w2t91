<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Models\Cohort;
use App\Models\CohortAssignment;
use App\Models\ExerciseAttempt;
use App\Models\SecurityExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityTrainingController extends Controller
{
    use AuthorizesRecordAccess;
    // --- Exercises ---

    public function indexExercises(Request $request): JsonResponse
    {
        $query = SecurityExercise::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('difficulty')) {
            $query->where('difficulty', $request->difficulty);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->is_published === 'true');
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function storeExercise(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'required|string|max:100',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'max_score' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            'scoring_rules' => 'nullable|array',
            'configuration' => 'nullable|array',
            'is_published' => 'nullable|boolean',
        ]);

        $exercise = SecurityExercise::create($request->only([
            'title', 'description', 'type', 'difficulty',
            'max_score', 'passing_score', 'scoring_rules',
            'configuration', 'is_published',
        ]));

        return response()->json([
            'message' => 'Exercise created successfully.',
            'data' => $exercise,
        ], 201);
    }

    public function showExercise(Request $request, int $id): JsonResponse
    {
        $exercise = SecurityExercise::with('cohortAssignments')->findOrFail($id);
        $this->authorizeRecord($request, $exercise);

        return response()->json(['data' => $exercise]);
    }

    public function updateExercise(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'type' => 'sometimes|required|string|max:100',
            'difficulty' => 'nullable|string|in:easy,medium,hard',
            'max_score' => 'nullable|integer|min:1',
            'passing_score' => 'nullable|integer|min:0',
            'scoring_rules' => 'nullable|array',
            'configuration' => 'nullable|array',
            'is_published' => 'nullable|boolean',
        ]);

        $exercise = SecurityExercise::findOrFail($id);
        $this->authorizeMutation($request, $exercise);
        $exercise->update($request->only([
            'title', 'description', 'type', 'difficulty',
            'max_score', 'passing_score', 'scoring_rules',
            'configuration', 'is_published',
        ]));

        return response()->json([
            'message' => 'Exercise updated successfully.',
            'data' => $exercise->fresh(),
        ]);
    }

    // --- Cohorts ---

    public function indexCohorts(Request $request): JsonResponse
    {
        $query = Cohort::withCount('assignments');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function storeCohort(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
        ]);

        $cohort = Cohort::create($request->only(['name', 'description', 'is_active']));

        return response()->json([
            'message' => 'Cohort created successfully.',
            'data' => $cohort,
        ], 201);
    }

    // --- Cohort Assignment Publishing ---

    public function publishAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'security_exercise_id' => 'required|exists:security_exercises,id',
            'learner_ids' => 'required|array|min:1',
            'learner_ids.*' => 'exists:learners,id',
            'due_at' => 'nullable|date|after:now',
        ]);

        $exercise = SecurityExercise::findOrFail($request->security_exercise_id);

        if (!$exercise->is_published) {
            return response()->json([
                'error' => 'Exercise Not Published',
                'message' => 'Cannot assign an unpublished exercise to a cohort.',
            ], 422);
        }

        $assignments = [];
        $now = now();

        foreach ($request->learner_ids as $learnerId) {
            $existing = CohortAssignment::where('cohort_id', $request->cohort_id)
                ->where('security_exercise_id', $request->security_exercise_id)
                ->where('learner_id', $learnerId)
                ->first();

            if ($existing) {
                continue;
            }

            $assignments[] = CohortAssignment::create([
                'cohort_id' => $request->cohort_id,
                'security_exercise_id' => $request->security_exercise_id,
                'learner_id' => $learnerId,
                'assigned_at' => $now,
                'due_at' => $request->due_at,
                'status' => 'assigned',
            ]);
        }

        return response()->json([
            'message' => 'Assignments published successfully.',
            'assigned_count' => count($assignments),
        ], 201);
    }

    // --- Attempts ---

    public function startAttempt(Request $request): JsonResponse
    {
        $request->validate([
            'security_exercise_id' => 'required|exists:security_exercises,id',
            'learner_id' => 'required|exists:learners,id',
            'cohort_id' => 'nullable|exists:cohorts,id',
        ]);

        $exercise = SecurityExercise::findOrFail($request->security_exercise_id);

        if (!$exercise->is_published) {
            return response()->json([
                'error' => 'Exercise Not Published',
                'message' => 'Cannot attempt an unpublished exercise.',
            ], 422);
        }

        $attempt = ExerciseAttempt::create([
            'security_exercise_id' => $request->security_exercise_id,
            'learner_id' => $request->learner_id,
            'cohort_id' => $request->cohort_id,
            'status' => 'in_progress',
            'started_at' => now(),
            'action_trail' => [],
        ]);

        return response()->json([
            'message' => 'Attempt started.',
            'data' => $attempt,
        ], 201);
    }

    public function recordAction(Request $request, int $attemptId): JsonResponse
    {
        $request->validate([
            'action' => 'required|string|max:255',
            'data' => 'nullable|array',
        ]);

        $attempt = ExerciseAttempt::findOrFail($attemptId);
        $this->authorizeRecord($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'Actions can only be recorded for in-progress attempts.',
            ], 422);
        }

        $trail = $attempt->action_trail ?? [];
        $trail[] = [
            'action' => $request->action,
            'data' => $request->data,
            'timestamp' => now()->toIso8601String(),
        ];

        $attempt->update(['action_trail' => $trail]);

        return response()->json([
            'message' => 'Action recorded.',
            'action_count' => count($trail),
        ]);
    }

    public function submitAttempt(Request $request, int $attemptId): JsonResponse
    {
        $request->validate([
            'answers' => 'nullable|array',
        ]);

        $attempt = ExerciseAttempt::with('exercise')->findOrFail($attemptId);
        $this->authorizeRecord($request, $attempt);

        if ($attempt->status !== 'in_progress') {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'Only in-progress attempts can be submitted.',
            ], 422);
        }

        $exercise = $attempt->exercise;

        // Calculate score using scoring rules
        $score = $this->calculateScore($exercise, $request->answers ?? [], $attempt->action_trail ?? []);
        $passed = $score >= $exercise->passing_score;

        $attempt->update([
            'answers' => $request->answers,
            'score' => $score,
            'passed' => $passed,
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Update cohort assignment status if applicable
        if ($attempt->cohort_id) {
            CohortAssignment::where('cohort_id', $attempt->cohort_id)
                ->where('security_exercise_id', $attempt->security_exercise_id)
                ->where('learner_id', $attempt->learner_id)
                ->update(['status' => $passed ? 'passed' : 'failed']);
        }

        return response()->json([
            'message' => 'Attempt submitted and scored.',
            'data' => $attempt->fresh(),
        ]);
    }

    public function showAttempt(Request $request, int $attemptId): JsonResponse
    {
        $attempt = ExerciseAttempt::with(['exercise', 'learner', 'cohort'])->findOrFail($attemptId);
        $this->authorizeRecord($request, $attempt);

        return response()->json(['data' => $attempt]);
    }

    public function indexAttempts(Request $request): JsonResponse
    {
        $query = ExerciseAttempt::with(['exercise', 'learner']);

        if ($request->has('security_exercise_id')) {
            $query->where('security_exercise_id', $request->security_exercise_id);
        }

        if ($request->has('learner_id')) {
            $query->where('learner_id', $request->learner_id);
        }

        if ($request->has('cohort_id')) {
            $query->where('cohort_id', $request->cohort_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    /**
     * Calculate score based on exercise scoring rules.
     */
    private function calculateScore(SecurityExercise $exercise, array $answers, array $actionTrail): int
    {
        $scoringRules = $exercise->scoring_rules ?? [];

        if (empty($scoringRules)) {
            // Default: score based on number of answers provided relative to max
            $answerCount = count($answers);
            if ($answerCount === 0) {
                return 0;
            }
            return min($exercise->max_score, (int) round(($answerCount / max($answerCount, 1)) * $exercise->max_score));
        }

        $score = 0;

        // Process configurable scoring rules
        foreach ($scoringRules as $rule) {
            $ruleType = $rule['type'] ?? 'fixed';

            switch ($ruleType) {
                case 'per_answer':
                    $pointsPerAnswer = $rule['points'] ?? 10;
                    $correctAnswers = $rule['correct_answers'] ?? [];
                    foreach ($answers as $key => $value) {
                        if (isset($correctAnswers[$key]) && $correctAnswers[$key] == $value) {
                            $score += $pointsPerAnswer;
                        }
                    }
                    break;

                case 'action_bonus':
                    $requiredActions = $rule['required_actions'] ?? [];
                    $bonusPoints = $rule['points'] ?? 5;
                    $performedActions = array_column($actionTrail, 'action');
                    foreach ($requiredActions as $action) {
                        if (in_array($action, $performedActions)) {
                            $score += $bonusPoints;
                        }
                    }
                    break;

                case 'completion_bonus':
                    if (!empty($answers) || !empty($actionTrail)) {
                        $score += $rule['points'] ?? 10;
                    }
                    break;

                case 'fixed':
                    $score += $rule['points'] ?? 0;
                    break;
            }
        }

        return min($score, $exercise->max_score);
    }
}
