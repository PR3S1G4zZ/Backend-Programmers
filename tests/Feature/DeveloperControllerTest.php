<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\DeveloperProfile;
use App\Models\Project;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeveloperControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that index() returns paginated developers correctly.
     */
    public function testIndexReturnsPaginatedDevelopers(): void
    {
        // Create a company user to act as authenticated user
        $company = User::factory()->company()->create();

        // Create multiple developers with profiles
        $developers = User::factory()
            ->count(20)
            ->programmer()
            ->create();

        // Create developer profiles for each developer
        foreach ($developers as $developer) {
            DeveloperProfile::factory()->create([
                'user_id' => $developer->id,
                'headline' => 'Senior Developer',
                'location' => 'Bogota',
                'hourly_rate' => 50.00,
                'availability' => 'available',
            ]);
        }

        // Make authenticated request
        $response = $this->actingAs($company, 'sanctum')
            ->getJson('/api/developers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'current_page',
                'data',
                'first_page_url',
                'from',
                'last_page',
                'last_page_url',
                'links',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total',
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('per_page', 15);

        // Verify the data structure of returned developers
        $this->assertCount(15, $response->json('data'));
    }

    /**
     * Test that show() returns complete developer details.
     */
    public function testShowReturnsDeveloperDetails(): void
    {
        // Create a company user to act as authenticated user
        $company = User::factory()->company()->create();

        // Create a developer with profile
        $developer = User::factory()->programmer()->create([
            'name' => 'Juan',
            'lastname' => 'Perez',
        ]);

        $profile = DeveloperProfile::factory()->create([
            'user_id' => $developer->id,
            'headline' => 'Full Stack Developer',
            'bio' => 'Experienced developer with 5 years of experience',
            'location' => 'Medellin',
            'hourly_rate' => 75.00,
            'experience_years' => 5,
            'availability' => 'available',
            'skills' => json_encode(['PHP', 'Laravel', 'React', 'Node.js']),
            'languages' => json_encode(['Spanish', 'English']),
            'links' => json_encode(['https://github.com/juanperez']),
        ]);

        // Create a completed project with application
        $project = Project::factory()->completed()->create([
            'company_id' => $company->id,
        ]);

        Application::factory()->accepted()->create([
            'project_id' => $project->id,
            'developer_id' => $developer->id,
        ]);

        // Create a review for the developer
        $review = Review::factory()->create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'developer_id' => $developer->id,
            'rating' => 5,
            'comment' => 'Great developer, highly recommended!',
        ]);

        // Make authenticated request
        $response = $this->actingAs($company, 'sanctum')
            ->getJson("/api/developers/{$developer->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'title',
                    'location',
                    'hourlyRate',
                    'rating',
                    'reviewsCount',
                    'completedProjects',
                    'availability',
                    'skills',
                    'experience',
                    'experience_details',
                    'languages',
                    'bio',
                    'links',
                    'lastActive',
                    'isVerified',
                    'joinedAt',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Juan Perez')
            ->assertJsonPath('data.email', $developer->email)
            ->assertJsonPath('data.title', 'Full Stack Developer')
            ->assertJsonPath('data.location', 'Medellin')
            ->assertJsonPath('data.hourlyRate', 75)
            ->assertJsonPath('data.rating', 5)
            ->assertJsonPath('data.reviewsCount', 1)
            ->assertJsonPath('data.completedProjects', 1)
            ->assertJsonPath('data.availability', 'available');
    }

    /**
     * Test that the search filter works correctly.
     */
    public function testIndexFiltersBySearch(): void
    {
        // Create a company user to act as authenticated user
        $company = User::factory()->company()->create();

        // Create developers with specific names - using unique names
        $developer1 = User::factory()->programmer()->create([
            'name' => 'Carlos',
            'lastname' => 'Alvarez',
        ]);
        DeveloperProfile::factory()->create(['user_id' => $developer1->id]);

        $developer2 = User::factory()->programmer()->create([
            'name' => 'Sofia',
            'lastname' => 'Muñoz',
        ]);
        DeveloperProfile::factory()->create(['user_id' => $developer2->id]);

        $developer3 = User::factory()->programmer()->create([
            'name' => 'Miguel',
            'lastname' => 'Sanchez',
        ]);
        DeveloperProfile::factory()->create(['user_id' => $developer3->id]);

        // Test searching by name
        $response = $this->actingAs($company, 'sanctum')
            ->getJson('/api/developers?search=Carlos');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Carlos Alvarez', $response->json('data.0.name'));

        // Test searching by lastname
        $response = $this->actingAs($company, 'sanctum')
            ->getJson('/api/developers?search=Muñoz');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Sofia Muñoz', $response->json('data.0.name'));

        // Test searching with partial match
        $response = $this->actingAs($company, 'sanctum')
            ->getJson('/api/developers?search=Sanchez');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Miguel Sanchez', $response->json('data.0.name'));

        // Test searching with no results
        $response = $this->actingAs($company, 'sanctum')
            ->getJson('/api/developers?search=NonExistent');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertCount(0, $response->json('data'));
    }
}
