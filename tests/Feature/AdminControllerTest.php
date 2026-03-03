<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── HELPERS ──────────────────────────────────────────────────────────────

    private function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        return $admin;
    }

    // ─── CREATE USER ──────────────────────────────────────────────────────────

    public function test_admin_can_create_user(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/users', [
            'name'      => 'Nuevo',
            'lastname'  => 'Usuario',
            'email'     => 'nuevo@example.com',
            'user_type' => 'programmer',
            'password'  => 'Admin1@Test',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.email', 'nuevo@example.com');

        $this->assertDatabaseHas('users', ['email' => 'nuevo@example.com']);
    }

    // ─── ACCESS CONTROL ───────────────────────────────────────────────────────

    public function test_non_admin_cannot_access_admin_routes(): void
    {
        $programmer = User::factory()->programmer()->create();
        Sanctum::actingAs($programmer);

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(403);
    }

    // ─── LIST USERS ───────────────────────────────────────────────────────────

    public function test_admin_can_list_users(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(3)->programmer()->create();

        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'users', 'pagination']);

        $this->assertGreaterThanOrEqual(1, count($response->json('users')));
    }

    public function test_admin_can_filter_users_by_type(): void
    {
        $this->actingAsAdmin();

        User::factory()->count(2)->programmer()->create();
        User::factory()->count(2)->company()->create();

        $response = $this->getJson('/api/admin/users?user_type=programmer');

        $response->assertStatus(200);

        $users = $response->json('users');
        foreach ($users as $user) {
            $this->assertEquals('programmer', $user['user_type']);
        }
    }

    public function test_admin_can_search_users(): void
    {
        $this->actingAsAdmin();

        User::factory()->programmer()->create(['name' => 'Buscable', 'email' => 'buscable@test.com']);
        User::factory()->programmer()->create(['name' => 'Otro', 'email' => 'otro@test.com']);

        $response = $this->getJson('/api/admin/users?search=Buscable');

        $response->assertStatus(200);

        $users = $response->json('users');
        $this->assertGreaterThanOrEqual(1, count($users));
    }

    // ─── GET SINGLE USER ──────────────────────────────────────────────────────

    public function test_admin_can_get_single_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->programmer()->create();

        $response = $this->getJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('user.id', $user->id);
    }

    // ─── UPDATE USER ──────────────────────────────────────────────────────────

    public function test_admin_can_update_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->programmer()->create();

        $response = $this->putJson("/api/admin/users/{$user->id}", [
            'name' => 'NombreActualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', [
            'id'   => $user->id,
            'name' => 'NombreActualizado',
        ]);
    }

    // ─── DELETE USER ──────────────────────────────────────────────────────────

    public function test_admin_can_delete_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->programmer()->create();

        $response = $this->deleteJson("/api/admin/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = $this->actingAsAdmin();

        $response = $this->deleteJson("/api/admin/users/{$admin->id}");

        $response->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'deleted_at' => null]);
    }

    // ─── RESTORE USER ─────────────────────────────────────────────────────────

    public function test_admin_can_restore_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->programmer()->create();
        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);

        $response = $this->postJson("/api/admin/users/{$user->id}/restore");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'deleted_at' => null]);
    }

    // ─── BAN USER ─────────────────────────────────────────────────────────────

    public function test_admin_can_ban_user(): void
    {
        $this->actingAsAdmin();

        $user = User::factory()->programmer()->create(['banned_at' => null]);

        $response = $this->postJson("/api/admin/users/{$user->id}/ban");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('banned', true);

        $this->assertDatabaseMissing('users', ['id' => $user->id, 'banned_at' => null]);
    }

    public function test_admin_cannot_ban_admin(): void
    {
        $this->actingAsAdmin();

        $otherAdmin = User::factory()->admin()->create();

        $response = $this->postJson("/api/admin/users/{$otherAdmin->id}/ban");

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    // ─── ADMIN PROJECTS ───────────────────────────────────────────────────────

    public function test_admin_can_view_projects(): void
    {
        $this->actingAsAdmin();

        Project::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/projects');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'projects', 'pagination']);
    }

    // ─── METRICS ──────────────────────────────────────────────────────────────

    public function test_admin_can_view_metrics(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/metrics');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data']);
    }
}
