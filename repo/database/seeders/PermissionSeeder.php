<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $permissions = [
            // User management
            ['name' => 'Manage Users', 'slug' => 'users.manage', 'description' => 'Create, update, and deactivate user accounts.'],
            ['name' => 'View Users', 'slug' => 'users.view', 'description' => 'View user profiles and listings.'],

            // Learner management
            ['name' => 'Create Learners', 'slug' => 'learners.create', 'description' => 'Create new learner profiles.'],
            ['name' => 'View Learners', 'slug' => 'learners.view', 'description' => 'View learner profiles.'],
            ['name' => 'Update Learners', 'slug' => 'learners.update', 'description' => 'Update learner profile data.'],
            ['name' => 'Import Learners', 'slug' => 'learners.import', 'description' => 'Bulk import learners via CSV/XLSX.'],
            ['name' => 'Manage Duplicates', 'slug' => 'learners.duplicates', 'description' => 'Review and resolve duplicate learner candidates.'],

            // Enrollment management
            ['name' => 'Create Enrollments', 'slug' => 'enrollments.create', 'description' => 'Create new enrollment records.'],
            ['name' => 'View Enrollments', 'slug' => 'enrollments.view', 'description' => 'View enrollment records and status.'],
            ['name' => 'Update Enrollments', 'slug' => 'enrollments.update', 'description' => 'Update enrollment data and trigger transitions.'],
            ['name' => 'Approve Enrollments', 'slug' => 'enrollments.approve', 'description' => 'Review and approve enrollment workflows.'],
            ['name' => 'Cancel Enrollments', 'slug' => 'enrollments.cancel', 'description' => 'Cancel enrollments and process refunds.'],

            // Booking management
            ['name' => 'Create Bookings', 'slug' => 'bookings.create', 'description' => 'Create and confirm resource bookings.'],
            ['name' => 'View Bookings', 'slug' => 'bookings.view', 'description' => 'View booking records and schedules.'],
            ['name' => 'Update Bookings', 'slug' => 'bookings.update', 'description' => 'Reschedule or modify bookings.'],
            ['name' => 'Cancel Bookings', 'slug' => 'bookings.cancel', 'description' => 'Cancel bookings.'],

            // Resource management
            ['name' => 'Manage Resources', 'slug' => 'resources.manage', 'description' => 'Create and manage resources and schedules.'],
            ['name' => 'View Resources', 'slug' => 'resources.view', 'description' => 'View resources and schedule availability.'],

            // Location management
            ['name' => 'Manage Locations', 'slug' => 'locations.manage', 'description' => 'Create and manage location records.'],
            ['name' => 'View Locations', 'slug' => 'locations.view', 'description' => 'View locations with obfuscated coordinates.'],
            ['name' => 'View Precise Locations', 'slug' => 'locations.view_precise', 'description' => 'View locations with precise coordinates.'],

            // Security training
            ['name' => 'Manage Exercises', 'slug' => 'exercises.manage', 'description' => 'Create and configure security training exercises.'],
            ['name' => 'View Exercises', 'slug' => 'exercises.view', 'description' => 'View exercises and attempt results.'],
            ['name' => 'Attempt Exercises', 'slug' => 'exercises.attempt', 'description' => 'Start and submit exercise attempts.'],

            // Reporting
            ['name' => 'Manage Reports', 'slug' => 'reports.manage', 'description' => 'Create report definitions and generate exports.'],
            ['name' => 'View Reports', 'slug' => 'reports.view', 'description' => 'View and download generated reports.'],

            // Audit
            ['name' => 'View Audit Trail', 'slug' => 'audit.view', 'description' => 'Query and view audit event logs.'],

            // Field placements
            ['name' => 'View Field Placements', 'slug' => 'placements.view', 'description' => 'View learner field placement assignments.'],
            ['name' => 'Manage Field Placements', 'slug' => 'placements.manage', 'description' => 'Create, update, and cancel field placements.'],
        ];

        foreach ($permissions as &$permission) {
            $permission['created_at'] = $now;
            $permission['updated_at'] = $now;
        }

        DB::table('permissions')->insert($permissions);

        // Map permissions to roles
        $rolePermissions = [
            'admin' => [
                'users.manage', 'users.view',
                'learners.create', 'learners.view', 'learners.update', 'learners.import', 'learners.duplicates',
                'enrollments.create', 'enrollments.view', 'enrollments.update', 'enrollments.approve', 'enrollments.cancel',
                'bookings.create', 'bookings.view', 'bookings.update', 'bookings.cancel',
                'resources.manage', 'resources.view',
                'locations.manage', 'locations.view', 'locations.view_precise',
                'exercises.manage', 'exercises.view', 'exercises.attempt',
                'reports.manage', 'reports.view',
                'audit.view',
                'placements.view', 'placements.manage',
            ],
            'planner' => [
                'users.view',
                'learners.create', 'learners.view', 'learners.update', 'learners.import', 'learners.duplicates',
                'enrollments.create', 'enrollments.view', 'enrollments.update', 'enrollments.cancel',
                'bookings.create', 'bookings.view', 'bookings.update', 'bookings.cancel',
                'resources.manage', 'resources.view',
                'locations.manage', 'locations.view', 'locations.view_precise',
                'exercises.view',
                'reports.manage', 'reports.view',
                'placements.view', 'placements.manage',
            ],
            'reviewer' => [
                'users.view',
                'learners.view',
                'enrollments.view', 'enrollments.approve',
                'bookings.view',
                'resources.view',
                'locations.view',
                'exercises.view',
                'reports.view',
                'audit.view',
            ],
            'field_agent' => [
                'learners.create', 'learners.view', 'learners.update',
                'enrollments.create', 'enrollments.view',
                'bookings.create', 'bookings.view', 'bookings.update',
                'resources.view',
                'locations.view',
                'exercises.view', 'exercises.attempt',
                'placements.view', 'placements.manage',
            ],
        ];

        $roles = DB::table('roles')->pluck('id', 'slug');
        $permissionIds = DB::table('permissions')->pluck('id', 'slug');

        $pivotRows = [];
        foreach ($rolePermissions as $roleSlug => $permSlugs) {
            foreach ($permSlugs as $permSlug) {
                $pivotRows[] = [
                    'role_id' => $roles[$roleSlug],
                    'permission_id' => $permissionIds[$permSlug],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('role_permission')->insert($pivotRows);
    }
}
