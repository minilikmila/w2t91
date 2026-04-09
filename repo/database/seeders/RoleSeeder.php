<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'admin',
                'description' => 'Full system access including user management, audit trails, and all operational functions.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Planner',
                'slug' => 'planner',
                'description' => 'Manages enrollments, scheduling, resource allocation, and location operations.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Reviewer',
                'slug' => 'reviewer',
                'description' => 'Reviews and approves enrollment workflows and manages approval queues.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Field Agent',
                'slug' => 'field_agent',
                'description' => 'Field-level access for learner interactions, bookings, and exercise facilitation.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('roles')->insert($roles);
    }
}
