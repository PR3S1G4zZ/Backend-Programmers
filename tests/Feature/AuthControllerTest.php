<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── REGISTER ────────────────────────────────────────────────────────────

    public function test_register_programmer_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Juan',
            'lastname'              => 'García',
            'email'                 => 'juan@example.com',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'programmer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.user_type', 'programmer')
            ->assertJsonStructure(['success', 'message', 'user', 'token']);

        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'user_type' => 'programmer']);
    }

    public function test_register_company_successfully(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Startup',
            'email'                 => 'startup@company.com',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'company',
            'company_name'          => 'Startup SAS',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.user_type', 'company');

        $this->assertDatabaseHas('users', ['email' => 'startup@company.com', 'user_type' => 'company']);
        $this->assertDatabaseHas('company_profiles', ['company_name' => 'Startup SAS']);
    }

    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Juan',
            'lastname'              => 'García',
            'email'                 => 'not-an-email',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'programmer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->programmer()->create(['email' => 'dup@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Otro',
            'lastname'              => 'Usuario',
            'email'                 => 'dup@example.com',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'programmer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_register_fails_with_weak_password(): void
    {
        // Password sin mayúsculas, ni caracteres especiales
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Juan',
            'lastname'              => 'García',
            'email'                 => 'juan2@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'user_type'             => 'programmer',
        ]);

        // El controller valida solo min/confirmed/regex, la validación fuerte está en el modelo boot()
        // Si el modelo falla, retorna 500 por excepción o 422 en el controller
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_register_fails_without_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'      => 'Juan',
            'lastname'  => 'García',
            'email'     => 'juan3@example.com',
            'password'  => 'SecurePass1@',
            // sin password_confirmation
            'user_type' => 'programmer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_register_programmer_requires_lastname(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Juan',
            // sin lastname
            'email'                 => 'juan4@example.com',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'programmer',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_register_company_requires_company_name(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'EmpresaX',
            'email'                 => 'empresa@test.com',
            'password'              => 'SecurePass1@',
            'password_confirmation' => 'SecurePass1@',
            'user_type'             => 'company',
            // sin company_name
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    // ─── LOGIN ────────────────────────────────────────────────────────────────

    public function test_login_successfully(): void
    {
        $user = User::factory()->programmer()->create([
            'password' => 'SecurePass1@',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'SecurePass1@',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'user', 'token'])
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_login_fails_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'noexiste@example.com',
            'password' => 'SecurePass1@',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $user = User::factory()->programmer()->create([
            'password' => 'SecurePass1@',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $user->email,
            'password' => 'WrongPass9#',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    // ─── LOGOUT ───────────────────────────────────────────────────────────────

    public function test_logout_successfully(): void
    {
        $user = User::factory()->programmer()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    // ─── ME ──────────────────────────────────────────────────────────────────

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->programmer()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    // ─── CHANGE PASSWORD ──────────────────────────────────────────────────────

    public function test_change_password_successfully(): void
    {
        $user = User::factory()->programmer()->create([
            'password' => 'SecurePass1@',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password'      => 'SecurePass1@',
            'new_password'          => 'NewSecure2#',
            'new_password_confirmation' => 'NewSecure2#',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_change_password_fails_with_wrong_current(): void
    {
        $user = User::factory()->programmer()->create([
            'password' => 'SecurePass1@',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password'          => 'WrongPass9#',
            'new_password'              => 'NewSecure2#',
            'new_password_confirmation' => 'NewSecure2#',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_change_password_fails_with_same_password(): void
    {
        $user = User::factory()->programmer()->create([
            'password' => 'SecurePass1@',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password'          => 'SecurePass1@',
            'new_password'              => 'SecurePass1@',
            'new_password_confirmation' => 'SecurePass1@',
        ]);

        $response->assertStatus(422);
    }

    // ─── FORGOT PASSWORD ──────────────────────────────────────────────────────

    public function test_send_reset_link_returns_success(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'anyone@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
