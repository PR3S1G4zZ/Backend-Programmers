<?php

namespace Tests\Feature;

use App\Events\ApplicationAccepted;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApplicationControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── APPLY ────────────────────────────────────────────────────────────────

    public function test_programmer_can_apply_to_project(): void
    {
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create(['status' => 'open']);

        Sanctum::actingAs($programmer);

        $response = $this->postJson("/api/projects/{$project->id}/apply", [
            'cover_letter' => 'Estoy muy interesado en este proyecto.',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('applications', [
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
            'status'       => 'pending',
        ]);
    }

    public function test_programmer_cannot_apply_twice(): void
    {
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create(['status' => 'open']);

        Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($programmer);

        $response = $this->postJson("/api/projects/{$project->id}/apply", [
            'cover_letter' => 'Segunda aplicación.',
        ]);

        $response->assertStatus(409);
    }

    public function test_company_cannot_apply_to_project(): void
    {
        $company = User::factory()->company()->create();
        $project = Project::factory()->create();

        Sanctum::actingAs($company);

        $response = $this->postJson("/api/projects/{$project->id}/apply");

        $response->assertStatus(403);
    }

    // ─── MY APPLICATIONS ──────────────────────────────────────────────────────

    public function test_programmer_sees_own_applications(): void
    {
        $programmer  = User::factory()->programmer()->create();
        $programmer2 = User::factory()->programmer()->create();

        Application::factory()->count(2)->create(['developer_id' => $programmer->id]);
        Application::factory()->count(3)->create(['developer_id' => $programmer2->id]);

        Sanctum::actingAs($programmer);

        $response = $this->getJson('/api/applications/mine');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    // ─── PROJECT APPLICATIONS ─────────────────────────────────────────────────

    public function test_company_sees_project_applications(): void
    {
        $company    = User::factory()->company()->create();
        $project    = Project::factory()->create(['company_id' => $company->id]);
        $programmer = User::factory()->programmer()->create();

        Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson("/api/projects/{$project->id}/applications");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    // ─── ACCEPT ───────────────────────────────────────────────────────────────

    public function test_company_can_accept_application(): void
    {
        Event::fake();

        $company    = User::factory()->company()->create();
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create([
            'company_id' => $company->id,
            'status'     => 'open',
            'budget_min' => 0, // Sin pago para simplificar test
        ]);

        $application = Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($company);

        $response = $this->postJson("/api/applications/{$application->id}/accept");

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'accepted',
        ]);

        // El proyecto no pasa a in_progress al aceptar la aplicación, se mantiene en open
        $this->assertDatabaseHas('projects', [
            'id'     => $project->id,
            'status' => 'open',
        ]);
    }

    // ─── REJECT ───────────────────────────────────────────────────────────────

    public function test_company_can_reject_application(): void
    {
        $company    = User::factory()->company()->create();
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create([
            'company_id' => $company->id,
            'status'     => 'open',
        ]);

        $application = Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($company);

        $response = $this->postJson("/api/applications/{$application->id}/reject");

        $response->assertStatus(200);

        $this->assertDatabaseHas('applications', [
            'id'     => $application->id,
            'status' => 'rejected',
        ]);
    }

    // ─── AUTHORIZATION ────────────────────────────────────────────────────────

    public function test_non_owner_cannot_accept_application(): void
    {
        $company1   = User::factory()->company()->create();
        $company2   = User::factory()->company()->create();
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create([
            'company_id' => $company1->id,
            'budget_min' => 0,
        ]);

        $application = Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        // company2 intenta aceptar una aplicación que pertenece al proyecto de company1
        Sanctum::actingAs($company2);

        $response = $this->postJson("/api/applications/{$application->id}/accept");

        $response->assertStatus(403);
    }

    // ─── EVENT / LISTENER ─────────────────────────────────────────────────────

    public function test_accept_creates_conversation(): void
    {
        Event::fake([ApplicationAccepted::class]);

        $company    = User::factory()->company()->create();
        $programmer = User::factory()->programmer()->create();
        $project    = Project::factory()->create([
            'company_id' => $company->id,
            'status'     => 'open',
            'budget_min' => 0,
        ]);

        $application = Application::factory()->pending()->create([
            'project_id'   => $project->id,
            'developer_id' => $programmer->id,
        ]);

        Sanctum::actingAs($company);

        $this->postJson("/api/applications/{$application->id}/accept");

        Event::assertDispatched(ApplicationAccepted::class, function ($event) use ($application) {
            return $event->application->id === $application->id;
        });
    }
}
