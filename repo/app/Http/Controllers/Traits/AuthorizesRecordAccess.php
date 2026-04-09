<?php

namespace App\Http\Controllers\Traits;

use App\Models\Booking;
use App\Models\Enrollment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides object-level authorization helpers for controllers.
 *
 * Admin and planner roles have unrestricted access. Reviewer and field_agent
 * roles are scoped: they can only access records they created, are assigned to,
 * or are explicitly linked to via a verified ownership chain.
 */
trait AuthorizesRecordAccess
{
    /**
     * Ensure the authenticated user may access the given record.
     * Throws 403 if the user lacks sufficient scope.
     */
    protected function authorizeRecord(Request $request, Model $record): void
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        // Admin and planner have unrestricted object access
        if ($user->hasRole('admin') || $user->hasRole('planner')) {
            return;
        }

        // Check ownership / assignment chains
        if ($this->userOwnsOrIsAssigned($user, $record)) {
            return;
        }

        throw new AccessDeniedHttpException('You do not have access to this record.');
    }

    /**
     * Determine if a user "owns" or is assigned to a record through any
     * recognised relationship chain.
     */
    private function userOwnsOrIsAssigned($user, Model $record): bool
    {
        // Direct user_id / created_by / booked_by ownership
        foreach (['user_id', 'created_by', 'booked_by', 'last_actor_id', 'reviewer_id'] as $col) {
            if (isset($record->{$col}) && (int) $record->{$col} === (int) $user->id) {
                return true;
            }
        }

        // Learner-linked records: check if the user has a concrete operational
        // relationship with the learner (e.g. created a booking or enrollment
        // for that learner).
        if (isset($record->learner_id)) {
            return $this->userHasOperationalLinkToLearner($user, (int) $record->learner_id);
        }

        return false;
    }

    /**
     * Check if the user has a concrete operational link to a learner through
     * bookings or enrollments they created.
     */
    private function userHasOperationalLinkToLearner($user, int $learnerId): bool
    {
        // User booked something for this learner
        $hasBooking = Booking::where('learner_id', $learnerId)
            ->where('booked_by', $user->id)
            ->exists();

        if ($hasBooking) {
            return true;
        }

        // User was the last actor on an enrollment for this learner
        $hasEnrollment = Enrollment::where('learner_id', $learnerId)
            ->where('last_actor_id', $user->id)
            ->exists();

        return $hasEnrollment;
    }

    /**
     * Ensure the authenticated user may mutate (update/delete) the given record.
     * More restrictive than read access.
     */
    protected function authorizeMutation(Request $request, Model $record): void
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if ($user->hasRole('admin') || $user->hasRole('planner')) {
            return;
        }

        // Only the creator/owner can mutate
        foreach (['user_id', 'created_by', 'booked_by', 'last_actor_id'] as $col) {
            if (isset($record->{$col}) && (int) $record->{$col} === (int) $user->id) {
                return;
            }
        }

        throw new AccessDeniedHttpException('You do not have permission to modify this record.');
    }
}
