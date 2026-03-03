<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── STORE ───────────────────────────────────────────────────────────────

    public function test_company_can_create_project(): void
    {
        $company = User::factory()->company()->create();

        Sanctum::actingAs($company);

        $response = $this->postJson('/api/projects', [
            'title'       => 'Nuevo Proyecto Laravel',
            'description' => 'Descripción del proyecto de prueba',
            'budget_min'  => 1000,
            'budget_max'  => 5000,
            'budget_type' => 'fixed',
            'priority'    => 'high',
            'level'       => 'mid',
            'status'      => 'open',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Nuevo Proyecto Laravel');

        $this->assertDatabaseHas('projects', [
            'title'      => 'Nuevo Proyecto Laravel',
            'company_id' => $company->id,
        ]);
    }

    public function test_programmer_cannot_create_project(): void
    {
        $programmer = User::factory()->programmer()->create();

        Sanctum::actingAs($programmer);

        $response = $this->postJson('/api/projects', [
            'title'       => 'Intento prohibido',
            'description' => 'Descripción',
            'priority'    => 'low',
        ]);

        $response->assertStatus(403);
    }

    public function test_unauthenticated_cannot_access_projects(): void
    {
        $response = $this->getJson('/api/projects');

        $response->assertStatus(401);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────

    public function test_company_can_update_own_project(): void
    {
        $company = User::factory()->company()->create();
        $project = Project::factory()->create(['company_id' => $company->id, 'status' => 'open']);

        Sanctum::actingAs($company);

        $response = $this->putJson("/api/projects/{$project->id}", [
            'title' => 'Título actualizado',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Título actualizado');

        $this->assertDatabaseHas('projects', [
            'id'    => $project->id,
            'title' => 'Título actualizado',
        ]);
    }

    public function test_company_cannot_update_other_company_project(): void
    {
        $company1 = User::factory()->company()->create();
        $company2 = User::factory()->company()->create();
        $project  = Project::factory()->create(['company_id' => $company1->id]);

        Sanctum::actingAs($company2);

        $response = $this->putJson("/api/projects/{$project->id}", [
            'title' => 'Intento no autorizado',
        ]);

        $response->assertStatus(403);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────────────

    public function test_company_can_delete_project(): void
    {
        $company = User::factory()->company()->create();
        $project = Project::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($company);

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }

    public function test_company_cannot_delete_project_with_accepted_devs(): void
    {
        $company     = User::factory()->company()->create();
        $programmer  = User::factory()->programmer()->create();
        $project     = Project::factory()->create(['company_id' => $company->id]);

        Application::factory()->accepted()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($company);

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('projects', ['id' => $project->id, 'deleted_at' => null]);
    }

    public function test_programmer_cannot_delete_project(): void
    {
        $company    = User::factory()->company()->create();
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create(['company_id' => $company->id]);

        Sanctum::actingAs($programmer);

        $response = $this->deleteJson("/api/projects/{$project->id}");

        $response->assertStatus(403);
    }

    // ─── INDEX ────────────────────────────────────────────────────────────────

    public function test_index_returns_paginated_projects(): void
    {
        $programmer = User::factory()->programmer()->create();

        // Crear algunos proyectos
        Project::factory()->count(3)->create(['status' => 'open']);

        Sanctum::actingAs($programmer);

        $response = $this->getJson('/api/projects?status=open');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta']);

        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────────

    public function test_show_returns_single_project(): void
    {
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create();

        Sanctum::actingAs($programmer);

        $response = $this->getJson("/api/projects/{$project->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $project->id);
    }

    // ─── COMPANY PROJECTS ─────────────────────────────────────────────────────

    public function test_company_projects_returns_only_own(): void
    {
        $company1 = User::factory()->company()->create();
        $company2 = User::factory()->company()->create();

        Project::factory()->count(2)->create(['company_id' => $company1->id]);
        Project::factory()->count(3)->create(['company_id' => $company2->id]);

        Sanctum::actingAs($company1);

        $response = $this->getJson('/api/company/projects');

        $response->assertStatus(200);

        $projects = $response->json('data');
        $this->assertCount(2, $projects);

        foreach ($projects as $p) {
            $this->assertEquals($company1->id, $p['company_id']);
        }
    }

    public function test_programmer_cannot_access_company_projects(): void
    {
        $programmer = User::factory()->programmer()->create();

        Sanctum::actingAs($programmer);

        $response = $this->getJson('/api/company/projects');

        $response->assertStatus(403);
    }
}
