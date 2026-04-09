<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Value object representing workflow configuration for an enrollment.
 * Stored as JSON in enrollments.workflow_metadata.
 */
class ApprovalWorkflow extends Model
{
    // This is not backed by a table — used as a structured helper.
    protected $guarded = [];
    public $exists = false;

    /**
     * Build default workflow metadata for an enrollment.
     */
    public static function buildDefault(Learner $learner, int $levels = 1): array
    {
        $isMinor = $learner->isMinor();
        $hasGuardian = !empty($learner->guardian_contact);

        // Minors require at least 2 levels and guardian approval
        if ($isMinor) {
            $levels = max($levels, 2);
        }

        $levels = min($levels, 3);

        $workflow = [
            'levels' => $levels,
            'requires_guardian_approval' => $isMinor,
            'guardian_contact_verified' => $isMinor && $hasGuardian,
            'conditions' => [],
            'level_config' => [],
        ];

        // Add conditional branching rules
        if ($isMinor) {
            $workflow['conditions'][] = [
                'type' => 'minor_check',
                'field' => 'date_of_birth',
                'rule' => 'age_under_18',
                'action' => 'require_guardian_approval',
            ];

            if (!$hasGuardian) {
                $workflow['conditions'][] = [
                    'type' => 'blocking',
                    'field' => 'guardian_contact',
                    'rule' => 'required_for_minor',
                    'action' => 'block_advancement',
                    'message' => 'Guardian contact required for minor learners.',
                ];
            }
        }

        // Configure each approval level
        for ($i = 1; $i <= $levels; $i++) {
            $config = [
                'level' => $i,
                'required_role' => 'reviewer',
                'auto_assign' => false,
            ];

            if ($i === 2 && $isMinor) {
                $config['required_role'] = 'admin';
                $config['description'] = 'Guardian approval verification for minor learner.';
            }

            $workflow['level_config'][] = $config;
        }

        return $workflow;
    }

    /**
     * Check if a workflow allows advancement to the next approval level.
     */
    public static function canAdvance(array $workflowMetadata, Learner $learner): array
    {
        $blockers = [];

        foreach ($workflowMetadata['conditions'] ?? [] as $condition) {
            if ($condition['type'] === 'blocking') {
                if ($condition['rule'] === 'required_for_minor' && $learner->isMinor()) {
                    if (empty($learner->guardian_contact)) {
                        $blockers[] = $condition['message'] ?? 'Condition not met: ' . $condition['field'];
                    }
                }
            }
        }

        return [
            'can_advance' => empty($blockers),
            'blockers' => $blockers,
        ];
    }
}
