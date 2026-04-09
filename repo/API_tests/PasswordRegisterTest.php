<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordRegisterTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $perm = Permission::create(['name' => 'Manage Users', 'slug' => 'users.manage']);
        $role->permissions()->attach($perm);

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'pw-reg-token-padded-to-exactly-sixty-four-characters-long-here!';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_register_rejects_11_char_password(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/auth/register', [
                'username' => 'newuser',
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'Abcdefgh1!x',
            ]);

        $response->assertStatus(422);
    }

    public function test_register_accepts_12_char_password(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/auth/register', [
                'username' => 'newuser',
                'name' => 'New User',
                'email' => 'new@example.com',
                'password' => 'Abcdefgh1!xy',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['username' => 'newuser']);
    }

    public function test_register_rejects_no_special_char(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/auth/register', [
                'username' => 'newuser2',
                'name' => 'New User',
                'email' => 'new2@example.com',
                'password' => 'Abcdefgh12xy',
            ]);

        $response->assertStatus(422);
    }

    public function test_register_rejects_no_uppercase(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/auth/register', [
                'username' => 'newuser3',
                'name' => 'New User',
                'email' => 'new3@example.com',
                'password' => 'abcdefgh1!xy',
            ]);

        $response->assertStatus(422);
    }
}
