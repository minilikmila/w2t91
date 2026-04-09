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

class LearnerSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        foreach (['learners.view', 'learners.create', 'learners.update'] as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'search-token-padded-to-exactly-sixty-four-characters-exactly-ok';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_search_email_populates_on_create(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/learners', [
                'first_name' => 'Alice',
                'last_name' => 'Test',
                'email' => 'Alice.Test@Example.COM',
            ]);

        $response->assertStatus(201);

        $learner = Learner::first();
        $this->assertEquals('alice.test@example.com', $learner->search_email);
    }

    public function test_search_phone_populates_on_create(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->postJson('/api/learners', [
                'first_name' => 'Bob',
                'last_name' => 'Test',
                'phone' => '+1 (555) 123-4567',
            ]);

        $response->assertStatus(201);

        $learner = Learner::first();
        $this->assertEquals('15551234567', $learner->search_phone);
    }

    public function test_search_by_name_returns_results(): void
    {
        Learner::create(['first_name' => 'Alice', 'last_name' => 'Wonder']);
        Learner::create(['first_name' => 'Bob', 'last_name' => 'Builder']);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/learners?search=Alice');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_by_email_uses_searchable_column(): void
    {
        $learner = Learner::create([
            'first_name' => 'Carol',
            'last_name' => 'Test',
            'email' => 'carol@example.com',
            'search_email' => 'carol@example.com',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/learners?search=carol@example');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_search_by_phone_uses_searchable_column(): void
    {
        $learner = Learner::create([
            'first_name' => 'Dave',
            'last_name' => 'Test',
            'phone' => '+15559876543',
            'search_phone' => '15559876543',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->token}"])
            ->getJson('/api/learners?search=9876543');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }
}
