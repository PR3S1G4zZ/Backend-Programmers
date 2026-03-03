<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Application;
use App\Models\Milestone;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MilestoneControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $company;
    protected User $developer;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear empresa con wallet
        $this->company = User::factory()->company()->create();
        Wallet::factory()->create([
            'user_id' => $this->company->id,
            'balance' => 5000,
            'held_balance' => 0,
        ]);

        // Crear desarrollador con wallet
        $this->developer = User::factory()->programmer()->create();
        Wallet::factory()->create([
            'user_id' => $this->developer->id,
            'balance' => 0,
            'held_balance' => 0,
        ]);

        // Crear proyecto y aplicación aceptada
        $this->project = Project::factory()->create([
            'company_id' => $this->company->id,
        ]);

        Application::factory()->create([
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * Test: Developer Can Submit Milestone
     */
    public function testDeveloperCanSubmitMilestone(): void
    {
        Sanctum::actingAs($this->developer);

        // Crear milestone en progreso
        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'progress_status' => 'in_progress',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/submit", [
            'deliverables' => ['Código fuente', 'Documentación', 'Tests'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('progress_status', 'review')
            ->assertJsonPath('deliverables', ['Código fuente', 'Documentación', 'Tests']);
    }

    /**
     * Test: Company Can Approve Milestone
     */
    public function testCompanyCanApproveMilestone(): void
    {
        Sanctum::actingAs($this->company);

        // Fundar el proyecto primero
        $this->company->wallet->update([
            'held_balance' => 1000,
            'balance' => 4000,
        ]);

        // Crear milestone en revisión
        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'amount' => 1000,
            'progress_status' => 'review',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('progress_status', 'completed');

        // Verificar que se liberaron los fondos
        $this->company->wallet->refresh();
        $this->assertEquals(0, $this->company->wallet->held_balance);
        
        // Verificar que el developer recibió el pago
        $this->developer->wallet->refresh();
        $this->assertEquals(850, $this->developer->wallet->balance); // 1000 - 15% comisión
    }

    /**
     * Test: Company Can Reject Milestone
     */
    public function testCompanyCanRejectMilestone(): void
    {
        Sanctum::actingAs($this->company);

        // Crear milestone en revisión
        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'progress_status' => 'review',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/reject");

        $response->assertStatus(200)
            ->assertJsonPath('progress_status', 'in_progress');
    }

    /**
     * Test: Milestone Approval Releases Payment
     */
    public function testMilestoneApprovalReleasesPayment(): void
    {
        Sanctum::actingAs($this->company);

        // Fondos en garantía
        $this->company->wallet->update([
            'held_balance' => 2000,
            'balance' => 3000,
        ]);

        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'amount' => 2000,
            'progress_status' => 'review',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/approve");

        $response->assertStatus(200);

        // Verificar estados después de aprobación
        $this->company->wallet->refresh();
        $this->developer->wallet->refresh();

        $this->assertEquals(0, $this->company->wallet->held_balance);
        $this->assertEquals(1700, $this->developer->wallet->balance); // 2000 - 15%
    }

    /**
     * Test: Company Cannot Approve Non-Review Milestone
     */
    public function testCompanyCannotApproveNonReviewMilestone(): void
    {
        Sanctum::actingAs($this->company);

        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'progress_status' => 'in_progress',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/approve");

        $response->assertStatus(400)
            ->assertJsonPath('message', 'El hito no está en revisión.');
    }

    /**
     * Test: Unauthorized User Cannot Access Milestones
     */
    public function testUnauthorizedUserCannotAccessMilestones(): void
    {
        $otherUser = User::factory()->programmer()->create();
        Sanctum::actingAs($otherUser);

        $response = $this->getJson("/api/projects/{$this->project->id}/milestones");

        $response->assertStatus(403);
    }

    /**
     * Test: Developer Cannot Approve Milestone
     */
    public function testDeveloperCannotApproveMilestone(): void
    {
        Sanctum::actingAs($this->developer);

        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'progress_status' => 'review',
        ]);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}/approve");

        $response->assertStatus(403);
    }

    /**
     * Test: Company Can Create Milestone
     */
    public function testCompanyCanCreateMilestone(): void
    {
        Sanctum::actingAs($this->company);

        $response = $this->postJson("/api/projects/{$this->project->id}/milestones", [
            'title' => 'Fase 1: Desarrollo',
            'description' => 'Desarrollo de la funcionalidad principal',
            'amount' => 1500,
            'due_date' => now()->addDays(30)->toDateString(),
            'order' => 1,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('title', 'Fase 1: Desarrollo')
            ->assertJsonPath('amount', 1500);

        $this->assertDatabaseHas('milestones', [
            'project_id' => $this->project->id,
            'title' => 'Fase 1: Desarrollo',
        ]);
    }

    /**
     * Test: Company Can Update Milestone
     */
    public function testCompanyCanUpdateMilestone(): void
    {
        Sanctum::actingAs($this->company);

        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'Título original',
        ]);

        $response = $this->putJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}", [
            'title' => 'Título actualizado',
            'amount' => 2000,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('title', 'Título actualizado')
            ->assertJsonPath('amount', 2000);
    }

    /**
     * Test: Company Can Delete Milestone
     */
    public function testCompanyCanDeleteMilestone(): void
    {
        Sanctum::actingAs($this->company);

        $milestone = Milestone::factory()->create([
            'project_id' => $this->project->id,
        ]);

        $response = $this->deleteJson("/api/projects/{$this->project->id}/milestones/{$milestone->id}");

        $response->assertStatus(204);

        $this->assertSoftDeleted('milestones', [
            'id' => $milestone->id,
        ]);
    }
}
