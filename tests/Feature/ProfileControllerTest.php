<?php

namespace Tests\Feature;

use App\Models\DeveloperProfile;
use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── SHOW ─────────────────────────────────────────────────────────────────

    public function test_developer_can_view_profile(): void
    {
        $developer = User::factory()->programmer()->create();

        // Crear perfil de desarrollador usando la factory
        DeveloperProfile::factory()->create(['user_id' => $developer->id]);

        Sanctum::actingAs($developer);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user'    => ['id', 'name', 'email', 'user_type'],
                    'profile' => ['user_id'],
                ],
            ])
            ->assertJsonPath('data.user.id', $developer->id)
            ->assertJsonPath('data.user.user_type', 'programmer');
    }

    public function test_company_can_view_profile(): void
    {
        $company = User::factory()->company()->create();

        // CompanyProfile no tiene HasFactory; crearlo directamente
        CompanyProfile::create([
            'user_id'      => $company->id,
            'company_name' => 'Test Corp',
        ]);

        Sanctum::actingAs($company);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.id', $company->id)
            ->assertJsonPath('data.user.user_type', 'company')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user'    => ['id', 'name', 'email', 'user_type'],
                    'profile' => ['user_id'],
                ],
            ]);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────────────

    public function test_developer_can_update_profile(): void
    {
        $developer = User::factory()->programmer()->create();
        DeveloperProfile::factory()->create(['user_id' => $developer->id]);

        Sanctum::actingAs($developer);

        $response = $this->putJson('/api/profile', [
            'name'             => 'Juan',
            'headline'         => 'Backend Developer',
            'bio'              => 'Desarrollador con 5 años de experiencia.',
            'location'         => 'Bogotá',
            'country'          => 'Colombia',
            'hourly_rate'      => 50,
            'availability'     => 'available',
            'experience_years' => 5,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('developer_profiles', [
            'user_id'  => $developer->id,
            'headline' => 'Backend Developer',
        ]);
    }

    public function test_company_can_update_profile(): void
    {
        $company = User::factory()->company()->create();
        // CompanyProfile no tiene HasFactory; crearlo directamente
        CompanyProfile::create([
            'user_id'      => $company->id,
            'company_name' => 'Original Corp',
        ]);

        Sanctum::actingAs($company);

        $response = $this->putJson('/api/profile', [
            'company_name' => 'Mi Empresa SA',
            'website'      => 'https://miempresa.com',
            'about'        => 'Empresa de software dedicada a soluciones web.',
            'location'     => 'Medellín',
            'country'      => 'Colombia',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('company_profiles', [
            'user_id'      => $company->id,
            'company_name' => 'Mi Empresa SA',
        ]);
    }

    // ─── AUTHENTICATION ───────────────────────────────────────────────────────

    public function test_unauthenticated_cannot_access_profile(): void
    {
        $response = $this->getJson('/api/profile');

        $response->assertStatus(401);
    }
}
