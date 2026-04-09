<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\ReportDefinition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        foreach (['reports.view', 'reports.manage'] as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $this->user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'report-token-padded-to-exactly-sixty-four-characters-long-here!';
        ApiToken::create([
            'user_id' => $this->user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_create_report_definition(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/reports', [
                'name' => 'Active Learners',
                'type' => 'learners',
                'output_format' => 'csv',
                'filters' => ['status' => 'active'],
                'columns' => ['id', 'first_name', 'last_name', 'email'],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Active Learners')
            ->assertJsonPath('data.type', 'learners');
    }

    public function test_list_reports(): void
    {
        ReportDefinition::create([
            'name' => 'Report A', 'type' => 'learners', 'output_format' => 'csv', 'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/reports');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_generate_csv_report(): void
    {
        Learner::create(['first_name' => 'Alice', 'last_name' => 'Test', 'status' => 'active']);
        Learner::create(['first_name' => 'Bob', 'last_name' => 'Test', 'status' => 'active']);

        $report = ReportDefinition::create([
            'name' => 'Active Learners', 'type' => 'learners',
            'output_format' => 'csv', 'created_by' => $this->user->id,
            'filters' => ['status' => 'active'],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/reports/{$report->id}/generate");

        $response->assertStatus(200)
            ->assertJsonPath('data.format', 'csv')
            ->assertJsonPath('data.row_count', 2);
    }

    public function test_generate_json_report(): void
    {
        Learner::create(['first_name' => 'Alice', 'last_name' => 'Test', 'status' => 'active']);

        $report = ReportDefinition::create([
            'name' => 'JSON Report', 'type' => 'learners',
            'output_format' => 'json', 'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/reports/{$report->id}/generate");

        $response->assertStatus(200)
            ->assertJsonPath('data.format', 'json');
    }

    public function test_download_nonexistent_export_returns_404(): void
    {
        $report = ReportDefinition::create([
            'name' => 'Empty Report', 'type' => 'learners',
            'output_format' => 'csv', 'created_by' => $this->user->id,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/reports/{$report->id}/download");

        $response->assertStatus(404);
    }

    public function test_invalid_report_type_rejected(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/reports', [
                'name' => 'Bad Report',
                'type' => 'invalid_type',
                'output_format' => 'csv',
            ]);

        $response->assertStatus(422);
    }
}
