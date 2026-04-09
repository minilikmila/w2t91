<?php

namespace Tests\Unit;

use App\Models\Enrollment;
use PHPUnit\Framework\TestCase;

class EnrollmentStateTest extends TestCase
{
    public function test_draft_can_transition_to_pending_review(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_DRAFT]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_PENDING_REVIEW));
    }

    public function test_draft_cannot_transition_to_approved(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_DRAFT]);
        $this->assertFalse($enrollment->canTransitionTo(Enrollment::STATUS_APPROVED));
    }

    public function test_draft_cannot_transition_to_enrolled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_DRAFT]);
        $this->assertFalse($enrollment->canTransitionTo(Enrollment::STATUS_ENROLLED));
    }

    public function test_pending_review_can_transition_to_in_review(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_PENDING_REVIEW]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_IN_REVIEW));
    }

    public function test_pending_review_can_be_cancelled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_PENDING_REVIEW]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_CANCELLED));
    }

    public function test_in_review_can_be_approved(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_IN_REVIEW]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_APPROVED));
    }

    public function test_in_review_can_be_rejected(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_IN_REVIEW]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_REJECTED));
    }

    public function test_in_review_can_be_cancelled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_IN_REVIEW]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_CANCELLED));
    }

    public function test_approved_can_transition_to_enrolled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_APPROVED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_ENROLLED));
    }

    public function test_approved_can_transition_to_waitlisted(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_APPROVED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_WAITLISTED));
    }

    public function test_approved_can_be_cancelled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_APPROVED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_CANCELLED));
    }

    public function test_rejected_can_return_to_draft(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_REJECTED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_DRAFT));
    }

    public function test_rejected_cannot_transition_to_approved(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_REJECTED]);
        $this->assertFalse($enrollment->canTransitionTo(Enrollment::STATUS_APPROVED));
    }

    public function test_enrolled_can_be_cancelled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_ENROLLED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_CANCELLED));
    }

    public function test_enrolled_can_be_completed(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_ENROLLED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_COMPLETED));
    }

    public function test_waitlisted_can_transition_to_enrolled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_WAITLISTED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_ENROLLED));
    }

    public function test_waitlisted_can_be_cancelled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_WAITLISTED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_CANCELLED));
    }

    public function test_cancelled_can_transition_to_refunded(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_CANCELLED]);
        $this->assertTrue($enrollment->canTransitionTo(Enrollment::STATUS_REFUNDED));
    }

    public function test_cancelled_cannot_transition_to_enrolled(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_CANCELLED]);
        $this->assertFalse($enrollment->canTransitionTo(Enrollment::STATUS_ENROLLED));
    }

    public function test_completed_is_terminal(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_COMPLETED]);
        $this->assertEmpty($enrollment->getAllowedTransitions());
    }

    public function test_refunded_is_terminal(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_REFUNDED]);
        $this->assertEmpty($enrollment->getAllowedTransitions());
    }

    public function test_get_allowed_transitions_from_draft(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_DRAFT]);
        $this->assertEquals([Enrollment::STATUS_PENDING_REVIEW], $enrollment->getAllowedTransitions());
    }

    public function test_get_allowed_transitions_from_approved(): void
    {
        $enrollment = new Enrollment(['status' => Enrollment::STATUS_APPROVED]);
        $expected = [Enrollment::STATUS_ENROLLED, Enrollment::STATUS_WAITLISTED, Enrollment::STATUS_CANCELLED];
        $this->assertEquals($expected, $enrollment->getAllowedTransitions());
    }
}
