<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Project;
use App\Models\Application;
use App\Models\Milestone;
use App\Models\Wallet;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Exception;

class PaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PaymentService $paymentService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->paymentService = new PaymentService();
    }

    /**
     * Test 1: Verifica que fundProject() mueve fondos de balance a held_balance correctamente
     */
    public function test_fund_project_successfully(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(1000)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Fondos a depositar
        $fundAmount = 500;

        // Ejecutar fundProject
        $this->paymentService->fundProject($company, $fundAmount, $project);

        // Verificar que el balance se decrementó
        $wallet->refresh();
        $this->assertEquals(500, $wallet->balance, 'El balance debe decrementarse en el monto fondeado');

        // Verificar que el held_balance se increment6ó
        $this->assertEquals(500, $wallet->held_balance, 'El held_balance debe incrementarse en el monto fondeado');
    }

    /**
     * Test 2: Verifica que lanza excepción cuando no hay suficientes fondos
     */
    public function test_fund_project_throws_exception_when_insufficient_funds(): void
    {
        // Crear empresa con wallet con saldo insuficiente
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(100)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Intentar fondear más de lo disponible
        $fundAmount = 500;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Saldo insuficiente');

        $this->paymentService->fundProject($company, $fundAmount, $project);
    }

    /**
     * Test 3: Verifica que releaseMilestone() libera fondos correctamente
     */
    public function test_release_milestone_successfully(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(0)->withHeldBalance(1000)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Crear desarrollador aceptado
        $developer = User::factory()->programmer()->create();
        $developerWallet = Wallet::factory()->for($developer)->withBalance(0)->create();

        // Crear aplicación aceptada
        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Crear admin para recibir comisión
        $admin = User::factory()->admin()->create();
        $adminWallet = Wallet::factory()->for($admin)->withBalance(0)->create();

        // Liberar milestone
        $releaseAmount = 400;

        $this->paymentService->releaseMilestone($company, $releaseAmount, $project);

        // Verificar que el held_balance se decrementó
        $wallet->refresh();
        $this->assertEquals(600, $wallet->held_balance, 'El held_balance debe decrementarse');

        // Verificar que el desarrollador recibió su parte (400 / 1 = 400 - 20% comisión = 320)
        $developerWallet->refresh();
        $this->assertEquals(320, $developerWallet->balance, 'El desarrollador debe recibir el monto neto');
    }

    /**
     * Test 4: Verifica que lanza excepción cuando held_balance es insuficiente
     */
    public function test_release_milestone_throws_exception_when_insufficient_held_balance(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(0)->withHeldBalance(100)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Crear desarrollador aceptado
        $developer = User::factory()->programmer()->create();
        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Intentar liberar más de lo disponible en held_balance
        $releaseAmount = 500;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Fondos en garantía insuficientes');

        $this->paymentService->releaseMilestone($company, $releaseAmount, $project);
    }

    /**
     * Test 5: Verifica 20% comisión para menos de $500
     */
    public function test_release_funds_uses_correct_commission_rate_under_500(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(0)->withHeldBalance(400)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Crear desarrollador
        $developer = User::factory()->programmer()->create();
        $developerWallet = Wallet::factory()->for($developer)->withBalance(0)->create();

        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Liberar 400 (menos de 500, debe aplicar 20% comisión)
        $releaseAmount = 400;

        $this->paymentService->releaseMilestone($company, $releaseAmount, $project);

        // Verificar monto neto al desarrollador: 400 - 80 (20%) = 320
        $developerWallet->refresh();
        $this->assertEquals(320, $developerWallet->balance, 'El desarrollador debe recibir el monto neto (400 - 20% comisión)');
    }

    /**
     * Test 6: Verifica 15% comisión para $500 o más
     */
    public function test_release_funds_uses_correct_commission_rate_over_500(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(0)->withHeldBalance(1000)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Crear desarrollador
        $developer = User::factory()->programmer()->create();
        $developerWallet = Wallet::factory()->for($developer)->withBalance(0)->create();

        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Liberar 600 (más de 500, debe aplicar 15% comisión)
        $releaseAmount = 600;

        $this->paymentService->releaseMilestone($company, $releaseAmount, $project);

        // Verificar monto neto al desarrollador: 600 - 90 (15%) = 510
        $developerWallet->refresh();
        $this->assertEquals(510, $developerWallet->balance, 'El desarrollador debe recibir el monto neto (600 - 15% comisión)');
    }

    /**
     * Test 7: Verifica que held_balance se actualice correctamente
     */
    public function test_held_balance_is_updated_correctly(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(2000)->withHeldBalance(0)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();

        // Fondear proyecto con 1000
        $this->paymentService->fundProject($company, 1000, $project);

        $wallet->refresh();
        $this->assertEquals(1000, $wallet->held_balance, 'El held_balance debe ser 1000 después del fundProject');

        // Crear desarrollador aceptado
        $developer = User::factory()->programmer()->create();
        $developerWallet = Wallet::factory()->for($developer)->withBalance(0)->create();
        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Crear admin
        $admin = User::factory()->admin()->create();
        Wallet::factory()->for($admin)->withBalance(0)->create();

        // Liberar 500
        $this->paymentService->releaseMilestone($company, 500, $project);

        $wallet->refresh();
        $this->assertEquals(500, $wallet->held_balance, 'El held_balance debe ser 500 después de releaseMilestone');
    }

    /**
     * Test 8: Verifica el flujo completo de pago
     */
    public function test_process_project_payment_holds_and_releases_funds(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $companyWallet = Wallet::factory()->for($company)->withBalance(1000)->withHeldBalance(0)->create();

        // Crear desarrollador
        $developer = User::factory()->programmer()->create();
        $developerWallet = Wallet::factory()->for($developer)->withBalance(0)->create();

        // Crear proyecto
        $project = Project::factory()->for($company, 'company')->create();
        Application::factory()->for($project)->for($developer, 'developer')->accepted()->create();

        // Ejecutar proceso completo de pago
        $amount = 800;

        $this->paymentService->processProjectPayment($company, $developer, $amount, $project);

        // Verificar estados finales
        $companyWallet->refresh();
        $developerWallet->refresh();

        // Company: balance inicial 1000 - 800 = 200
        $this->assertEquals(200, $companyWallet->balance, 'El balance de la empresa debe ser 200');

        // Company held_balance: 0 (todo liberado)
        $this->assertEquals(0, $companyWallet->held_balance, 'El held_balance debe ser 0 después de release');

        // Developer: 800 >= 500, quindi 15% = 800 - 120 = 680
        $this->assertEquals(680, $developerWallet->balance, 'El desarrollador debe recibir 680 (800 - 15% comisión)');
    }

    /**
     * Test 9: Verifica que lanza excepción sin desarrolladores aceptados
     */
    public function test_release_funds_throws_exception_when_no_accepted_developers(): void
    {
        // Crear empresa con wallet
        $company = User::factory()->company()->create();
        $wallet = Wallet::factory()->for($company)->withBalance(0)->withHeldBalance(1000)->create();

        // Crear proyecto sin aplicaciones aceptadas
        $project = Project::factory()->for($company, 'company')->create();

        // Crear aplicación pendiente (no aceptada)
        $developer = User::factory()->programmer()->create();
        Application::factory()->for($project)->for($developer, 'developer')->pending()->create();

        // Intentar liberar fondos
        $releaseAmount = 500;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('No hay desarrolladores aceptados para liberar fondos');

        $this->paymentService->releaseMilestone($company, $releaseAmount, $project);
    }
}
