<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private Role $adminRole;
    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        \App\Models\Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage']);
        $this->adminRole->permissions()->attach(\App\Models\Permission::where('slug', 'users.manage')->first());

        $this->adminUser = User::create([
            'username' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('SecurePass1!'),
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->plainToken = 'test-token-string-64chars-padded-to-be-exactly-sixty-four-chars!';
        ApiToken::create([
            'user_id' => $this->adminUser->id,
            'token' => hash('sha256', $this->plainToken),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_login_success(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'SecurePass1!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message', 'token', 'token_type', 'expires_at',
                'user' => ['id', 'username', 'name', 'email', 'role'],
            ]);
    }

    public function test_login_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'WrongPassword1!',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'Unauthorized']);
    }

    public function test_login_nonexistent_user(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nobody',
            'password' => 'SecurePass1!',
        ]);

        $response->assertStatus(401);
    }

    public function test_login_lockout_after_five_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'username' => 'admin',
                'password' => 'WrongPassword!',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'username' => 'admin',
            'password' => 'SecurePass1!',
        ]);

        $response->assertStatus(423);
    }

    public function test_logout_success(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->plainToken}",
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully.']);
    }

    public function test_me_returns_user_profile(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->plainToken}",
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.username', 'admin')
            ->assertJsonPath('user.role.slug', 'admin');
    }

    public function test_unauthenticated_access_rejected(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_expired_token_rejected(): void
    {
        $expiredToken = 'expired-token-padded-to-sixty-four-characters-long-exactly-here!';
        ApiToken::create([
            'user_id' => $this->adminUser->id,
            'token' => hash('sha256', $expiredToken),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$expiredToken}",
        ])->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_login_missing_fields_returns_validation_error(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422);
    }
}
