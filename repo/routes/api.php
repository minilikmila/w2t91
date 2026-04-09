<?php

use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SecurityTrainingController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Public auth routes
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register'])
    ->middleware(['auth.token', 'permission:users.manage']);

// Protected auth routes
Route::middleware(['auth.token', 'check.lockout'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Learner routes
    Route::get('/learners', [LearnerController::class, 'index'])
        ->middleware('permission:learners.view');
    Route::post('/learners', [LearnerController::class, 'store'])
        ->middleware('permission:learners.create');
    Route::get('/learners/{id}', [LearnerController::class, 'show'])
        ->middleware('permission:learners.view');
    Route::put('/learners/{id}', [LearnerController::class, 'update'])
        ->middleware('permission:learners.update');
    Route::delete('/learners/{id}', [LearnerController::class, 'destroy'])
        ->middleware('permission:learners.update');

    // Import routes
    Route::post('/import/learners', [ImportController::class, 'importLearners'])
        ->middleware('permission:learners.import');

    // Enrollment routes
    Route::get('/enrollments', [EnrollmentController::class, 'index'])
        ->middleware('permission:enrollments.view');
    Route::post('/enrollments', [EnrollmentController::class, 'store'])
        ->middleware('permission:enrollments.create');
    Route::get('/enrollments/{id}', [EnrollmentController::class, 'show'])
        ->middleware('permission:enrollments.view');
    Route::put('/enrollments/{id}/transition', [EnrollmentController::class, 'transition'])
        ->middleware('permission:enrollments.update');
    Route::post('/enrollments/{id}/submit', [EnrollmentController::class, 'submitForReview'])
        ->middleware('permission:enrollments.update');
    Route::post('/enrollments/{id}/review', [EnrollmentController::class, 'beginReview'])
        ->middleware('permission:enrollments.approve');
    Route::get('/enrollments/{id}/workflow', [EnrollmentController::class, 'workflowStatus'])
        ->middleware('permission:enrollments.view');
    Route::post('/enrollments/{id}/cancel', [EnrollmentController::class, 'cancel'])
        ->middleware('permission:enrollments.cancel');
    Route::post('/enrollments/{id}/refund', [EnrollmentController::class, 'refund'])
        ->middleware('permission:enrollments.cancel');
    Route::get('/enrollments/{id}/refund-eligibility', [EnrollmentController::class, 'refundEligibility'])
        ->middleware('permission:enrollments.view');

    // Approval routes
    Route::get('/approvals', [ApprovalController::class, 'index'])
        ->middleware('permission:enrollments.approve');
    Route::get('/approvals/{id}', [ApprovalController::class, 'show'])
        ->middleware('permission:enrollments.approve');
    Route::post('/approvals/{id}/decide', [ApprovalController::class, 'decide'])
        ->middleware('permission:enrollments.approve');
    Route::post('/approvals/{id}/decide-sync', [ApprovalController::class, 'decideSync'])
        ->middleware('permission:enrollments.approve');
    Route::post('/approvals/{id}/claim', [ApprovalController::class, 'claim'])
        ->middleware('permission:enrollments.approve');

    // Booking routes
    Route::get('/bookings', [BookingController::class, 'index'])
        ->middleware('permission:bookings.view')->name('bookings.index');
    Route::post('/bookings', [BookingController::class, 'store'])
        ->middleware('permission:bookings.create')->name('bookings.store');
    Route::get('/bookings/{id}', [BookingController::class, 'show'])
        ->middleware('permission:bookings.view')->name('bookings.show');
    Route::post('/bookings/{id}/confirm', [BookingController::class, 'confirm'])
        ->middleware('permission:bookings.create')->name('bookings.confirm');
    Route::post('/bookings/{id}/cancel', [BookingController::class, 'cancel'])
        ->middleware('permission:bookings.cancel')->name('bookings.cancel');
    Route::put('/bookings/{id}/reschedule', [BookingController::class, 'reschedule'])
        ->middleware('permission:bookings.update')->name('bookings.reschedule');

    // Waitlist routes
    Route::get('/waitlist', [BookingController::class, 'waitlistIndex'])
        ->middleware('permission:bookings.view')->name('waitlist.index');
    Route::post('/waitlist', [BookingController::class, 'waitlist'])
        ->middleware('permission:bookings.create')->name('waitlist.store');
    Route::post('/waitlist/{id}/accept', [BookingController::class, 'acceptWaitlistOffer'])
        ->middleware('permission:bookings.create')->name('waitlist.accept');

    // Location routes
    Route::get('/locations', [LocationController::class, 'index'])
        ->middleware('permission:locations.view');
    Route::post('/locations', [LocationController::class, 'store'])
        ->middleware('permission:locations.manage');
    Route::get('/locations/nearby', [LocationController::class, 'nearby'])
        ->middleware('permission:locations.view');
    Route::get('/locations/{id}', [LocationController::class, 'show'])
        ->middleware('permission:locations.view');
    Route::put('/locations/{id}', [LocationController::class, 'update'])
        ->middleware('permission:locations.manage');
    Route::delete('/locations/{id}', [LocationController::class, 'destroy'])
        ->middleware('permission:locations.manage');
    Route::get('/locations/{id}/geofence', [LocationController::class, 'geofenceCheck'])
        ->middleware('permission:locations.view');

    // Security Training - Exercises
    Route::get('/exercises', [SecurityTrainingController::class, 'indexExercises'])
        ->middleware('permission:exercises.view');
    Route::post('/exercises', [SecurityTrainingController::class, 'storeExercise'])
        ->middleware('permission:exercises.manage');
    Route::get('/exercises/{id}', [SecurityTrainingController::class, 'showExercise'])
        ->middleware('permission:exercises.view');
    Route::put('/exercises/{id}', [SecurityTrainingController::class, 'updateExercise'])
        ->middleware('permission:exercises.manage');

    // Security Training - Cohorts
    Route::get('/cohorts', [SecurityTrainingController::class, 'indexCohorts'])
        ->middleware('permission:exercises.view');
    Route::post('/cohorts', [SecurityTrainingController::class, 'storeCohort'])
        ->middleware('permission:exercises.manage');
    Route::post('/cohorts/assign', [SecurityTrainingController::class, 'publishAssignment'])
        ->middleware('permission:exercises.manage');

    // Security Training - Attempts
    Route::get('/attempts', [SecurityTrainingController::class, 'indexAttempts'])
        ->middleware('permission:exercises.view');
    Route::post('/attempts', [SecurityTrainingController::class, 'startAttempt'])
        ->middleware('permission:exercises.attempt');
    Route::get('/attempts/{id}', [SecurityTrainingController::class, 'showAttempt'])
        ->middleware('permission:exercises.view');
    Route::post('/attempts/{id}/action', [SecurityTrainingController::class, 'recordAction'])
        ->middleware('permission:exercises.attempt');
    Route::post('/attempts/{id}/submit', [SecurityTrainingController::class, 'submitAttempt'])
        ->middleware('permission:exercises.attempt');

    // Audit routes
    Route::get('/audit', [AuditController::class, 'index'])
        ->middleware('permission:audit.view');
    Route::get('/audit/verify', [AuditController::class, 'verifyChain'])
        ->middleware('permission:audit.view');
    Route::get('/audit/{id}', [AuditController::class, 'show'])
        ->middleware('permission:audit.view');
    Route::get('/audit/entity/{entityType}/{entityId}', [AuditController::class, 'entityTrail'])
        ->middleware('permission:audit.view');

    // Report routes
    Route::get('/reports', [ReportController::class, 'index'])
        ->middleware('permission:reports.view');
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('permission:reports.manage');
    Route::get('/reports/{id}', [ReportController::class, 'show'])
        ->middleware('permission:reports.view');
    Route::put('/reports/{id}', [ReportController::class, 'update'])
        ->middleware('permission:reports.manage');
    Route::delete('/reports/{id}', [ReportController::class, 'destroy'])
        ->middleware('permission:reports.manage');
    Route::post('/reports/{id}/generate', [ReportController::class, 'generate'])
        ->middleware('permission:reports.manage');
    Route::get('/reports/{id}/download', [ReportController::class, 'download'])
        ->middleware('permission:reports.view');

    // Resource routes
    Route::get('/resources', [ResourceController::class, 'index'])
        ->middleware('permission:resources.view');
    Route::post('/resources', [ResourceController::class, 'store'])
        ->middleware('permission:resources.manage');
    Route::get('/resources/{id}', [ResourceController::class, 'show'])
        ->middleware('permission:resources.view');
    Route::put('/resources/{id}', [ResourceController::class, 'update'])
        ->middleware('permission:resources.manage');
    Route::delete('/resources/{id}', [ResourceController::class, 'destroy'])
        ->middleware('permission:resources.manage');

    // Schedule routes
    Route::get('/schedules', [ScheduleController::class, 'index'])
        ->middleware('permission:resources.view');
    Route::post('/schedules', [ScheduleController::class, 'store'])
        ->middleware('permission:resources.manage');
    Route::get('/schedules/{id}', [ScheduleController::class, 'show'])
        ->middleware('permission:resources.view');
    Route::put('/schedules/{id}', [ScheduleController::class, 'update'])
        ->middleware('permission:resources.manage');
    Route::delete('/schedules/{id}', [ScheduleController::class, 'destroy'])
        ->middleware('permission:resources.manage');
    Route::get('/schedules/{id}/slots', [ScheduleController::class, 'slots'])
        ->middleware('permission:resources.view');

    // Route routes
    Route::get('/routes', [RouteController::class, 'index'])
        ->middleware('permission:resources.view');
    Route::post('/routes', [RouteController::class, 'store'])
        ->middleware('permission:resources.manage');
    Route::get('/routes/{id}', [RouteController::class, 'show'])
        ->middleware('permission:resources.view');
    Route::put('/routes/{id}', [RouteController::class, 'update'])
        ->middleware('permission:resources.manage');
    Route::delete('/routes/{id}', [RouteController::class, 'destroy'])
        ->middleware('permission:resources.manage');
    Route::get('/routes/{id}/versions', [RouteController::class, 'versions'])
        ->middleware('permission:resources.view');
});
