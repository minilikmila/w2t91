<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LearnerApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $fieldAgentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $fieldRole = Role::create(['name' => 'Field Agent', 'slug' => 'field_agent']);

        $perms = ['learners.view', 'learners.create', 'learners.update', 'learners.import'];
        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $adminRole->permissions()->attach($perm);
            if (in_array($p, ['learners.view', 'learners.create', 'learners.update'])) {
                $fieldRole->permissions()->attach($perm);
            }
        }

        $admin = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $fieldAgent = User::create([
            'username' => 'agent', 'name' => 'Agent', 'email' => 'agent@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $fieldRole->id, 'is_active' => true,
        ]);

        $this->adminToken = 'admin-token-padded-to-exactly-sixty-four-characters-long-here!!';
        ApiToken::create([
            'user_id' => $admin->id,
            'token' => hash('sha256', $this->adminToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->fieldAgentToken = 'field-token-padded-to-exactly-sixty-four-characters-long-here!!';
        ApiToken::create([
            'user_id' => $fieldAgent->id,
            'token' => hash('sha256', $this->fieldAgentToken),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_create_learner(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson('/api/learners', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'date_of_birth' => '1990-05-15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.first_name', 'John')
            ->assertJsonPath('data.last_name', 'Doe');
    }

    public function test_create_learner_validation_error(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson('/api/learners', [
                'email' => 'john@example.com',
            ]);

        $response->assertStatus(422);
    }

    public function test_list_learners(): void
    {
        Learner::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        Learner::create(['first_name' => 'Bob', 'last_name' => 'Smith']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/learners');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_show_learner(): void
    {
        $learner = Learner::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/learners/{$learner->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Jane');
    }

    public function test_update_learner(): void
    {
        $learner = Learner::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->putJson("/api/learners/{$learner->id}", [
                'first_name' => 'Janet',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.first_name', 'Janet');
    }

    public function test_delete_learner(): void
    {
        $learner = Learner::create(['first_name' => 'Jane', 'last_name' => 'Doe']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->deleteJson("/api/learners/{$learner->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('learners', ['id' => $learner->id]);
    }

    public function test_search_learners(): void
    {
        Learner::create(['first_name' => 'Alice', 'last_name' => 'Wonder']);
        Learner::create(['first_name' => 'Bob', 'last_name' => 'Builder']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/learners?search=Alice');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_nonexistent_learner_returns_404(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/learners/99999');

        $response->assertStatus(404);
    }

    public function test_field_agent_cannot_import(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->postJson('/api/import/learners', []);

        $response->assertStatus(403);
    }
}
