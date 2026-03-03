<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\Application;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReviewControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $company;
    protected User $developer;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = User::factory()->company()->create();
        $this->developer = User::factory()->programmer()->create();

        $this->project = Project::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        Application::factory()->create([
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'status' => 'accepted',
        ]);
    }

    /**
     * Test: Index Returns Developer Reviews
     */
    public function testIndexReturnsDeveloperReviews(): void
    {
        Sanctum::actingAs($this->developer);

        // Crear reseñas para el developer (cada una con un proyecto diferente)
        for ($i = 0; $i < 5; $i++) {
            $project = Project::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'completed',
            ]);

            Application::factory()->create([
                'project_id' => $project->id,
                'developer_id' => $this->developer->id,
                'status' => 'accepted',
            ]);

            Review::factory()->create([
                'developer_id' => $this->developer->id,
                'company_id' => $this->company->id,
                'project_id' => $project->id,
            ]);
        }

        $response = $this->getJson('/api/reviews');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'project_id',
                            'company_id',
                            'developer_id',
                            'rating',
                            'comment',
                            'created_at',
                            'project' => [
                                'id',
                                'title',
                            ],
                            'company' => [
                                'id',
                                'name',
                            ],
                        ],
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        // Verificar que返回正确的评论数
        $response->assertJsonCount(5, 'data.data');
    }

    /**
     * Test: Store Review Validation
     */
    public function testStoreReviewValidation(): void
    {
        Sanctum::actingAs($this->company);

        // Intentar crear review sin datos requeridos
        $response = $this->postJson('/api/reviews', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['project_id', 'developer_id', 'rating']);

        // Rating inválido (menor a 1)
        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating inválido (mayor a 5)
        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 6,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test: Company Can Only Review Once
     */
    public function testCompanyCanOnlyReviewOnce(): void
    {
        Sanctum::actingAs($this->company);

        // Crear primera review
        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 5,
            'comment' => 'Excelente trabajo',
        ]);

        $response->assertStatus(201);

        // Intentar crear segunda review para el mismo proyecto y developer
        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 4,
            'comment' => 'Buen trabajo',
        ]);

        // Debe fallar por restricción unique
        $response->assertStatus(422);
    }

    /**
     * Test: Company Cannot Review Non-Owned Project
     */
    public function testCompanyCannotReviewNonOwnedProject(): void
    {
        $otherCompany = User::factory()->company()->create();
        Sanctum::actingAs($otherCompany);

        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 5,
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('message', 'No tienes permiso para revisar este proyecto');
    }

    /**
     * Test: Company Cannot Review Developer Not In Project
     */
    public function testCompanyCannotReviewDeveloperNotInProject(): void
    {
        $otherDeveloper = User::factory()->programmer()->create();
        Sanctum::actingAs($this->company);

        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $otherDeveloper->id,
            'rating' => 5,
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'El developer no ha trabajado en este proyecto');
    }

    /**
     * Test: Company Can Successfully Create Review
     */
    public function testCompanyCanSuccessfullyCreateReview(): void
    {
        Sanctum::actingAs($this->company);

        $response = $this->postJson('/api/reviews', [
            'project_id' => $this->project->id,
            'developer_id' => $this->developer->id,
            'rating' => 5,
            'comment' => 'Excelente desarrollador, muy profesional y puntuales.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.comment', 'Excelente desarrollador, muy profesional y puntuales.');

        // Verificar que se guardó en la base de datos
        $this->assertDatabaseHas('reviews', [
            'project_id' => $this->project->id,
            'company_id' => $this->company->id,
            'developer_id' => $this->developer->id,
            'rating' => 5,
        ]);
    }

    /**
     * Test: Show Single Review
     */
    public function testShowReview(): void
    {
        $review = Review::factory()->create([
            'developer_id' => $this->developer->id,
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
        ]);

        Sanctum::actingAs($this->company);

        $response = $this->getJson('/api/reviews/' . $review->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $review->id);
    }

    /**
     * Test: Unauthenticated User Cannot Access Reviews
     */
    public function testUnauthenticatedUserCannotAccessReviews(): void
    {
        $response = $this->getJson('/api/reviews');

        $response->assertStatus(401);
    }

    /**
     * Test: Developer Can Only See Their Reviews
     */
    public function testDeveloperCanOnlySeeTheirReviews(): void
    {
        $otherDeveloper = User::factory()->programmer()->create();

        // Crear reseñas para el developer actual (cada una con un proyecto diferente)
        for ($i = 0; $i < 3; $i++) {
            $project = Project::factory()->create([
                'company_id' => $this->company->id,
                'status' => 'completed',
            ]);

            Application::factory()->create([
                'project_id' => $project->id,
                'developer_id' => $this->developer->id,
                'status' => 'accepted',
            ]);

            Review::factory()->create([
                'developer_id' => $this->developer->id,
                'company_id' => $this->company->id,
                'project_id' => $project->id,
            ]);
        }

        // Crear reseña para el otro developer
        $otherProject = Project::factory()->create([
            'company_id' => $this->company->id,
            'status' => 'completed',
        ]);

        Application::factory()->create([
            'project_id' => $otherProject->id,
            'developer_id' => $otherDeveloper->id,
            'status' => 'accepted',
        ]);

        Review::factory()->create([
            'developer_id' => $otherDeveloper->id,
            'company_id' => $this->company->id,
            'project_id' => $otherProject->id,
        ]);

        Sanctum::actingAs($this->developer);

        $response = $this->getJson('/api/reviews');

        $response->assertStatus(200);
        // Solo debe ver sus propias reviews
        foreach ($response->json('data.data') as $review) {
            $this->assertEquals($this->developer->id, $review['developer_id']);
        }
    }
}
