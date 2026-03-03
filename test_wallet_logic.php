<?php

use App\Models\User;
use App\Models\Project;
use App\Models\Application;
use Illuminate\Support\Facades\DB;
use App\Services\PaymentService;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- Iniciando Test de Lógica de Billetera ---\n";

// 1. Setup Users
$company = User::where('email', 'company@test.com')->first();
if (!$company) {
    $company = User::factory()->create([
        'name' => 'Company',
        'lastname' => 'Test',
        'email' => 'company@test.com',
        'user_type' => 'company',
        'password' => 'Password123!'
    ]);
    echo "Cicada Empresa: {$company->email}\n";
} else {
    echo "Empresa existente: {$company->email}\n";
}

$developer = User::where('email', 'dev@test.com')->first();
if (!$developer) {
    $developer = User::factory()->create([
        'name' => 'Dev',
        'lastname' => 'Test',
        'email' => 'dev@test.com',
        'user_type' => 'programmer',
        'password' => 'Password123!'
    ]);
    echo "Creado Desarrollador: {$developer->email}\n";
} else {
    echo "Desarrollador existente: {$developer->email}\n";
}

// 2. Recharge Company Wallet
echo "\n--- Recargando Billetera de Empresa ---\n";
$paymentService = new PaymentService();
$companyWallet = $paymentService->getWallet($company);
$initialBalance = $companyWallet->balance;
echo "Saldo Inicial: $initialBalance\n";

$companyWallet->increment('balance', 1000);
$companyWallet->transactions()->create([
    'amount' => 1000,
    'type' => 'deposit',
    'description' => 'Recarga Test Script',
    'reference_type' => 'system',
    'reference_id' => 0
]);
echo "Saldo Nuevo: {$companyWallet->refresh()->balance}\n";


// 3. Create Project & Application
echo "\n--- Creando Proyecto y Aplicación ---\n";
$project = Project::create([
    'company_id' => $company->id,
    'title' => 'Proyecto Test Wallet ' . time(),
    'description' => 'Test de pago',
    'budget_min' => 500, // Amount to pay
    'status' => 'open'
]);

$application = Application::create([
    'project_id' => $project->id,
    'developer_id' => $developer->id,
    'status' => 'pending'
]);
echo "Proyecto ID: {$project->id}, Aplicación ID: {$application->id}\n";


// 4. Process Payment (Simulate Acceptance)
echo "\n--- Procesando Pago (Aceptando Candidato) ---\n";
try {
    $paymentService->processProjectPayment($company, $developer, 500, $project);
    
    // Refresh
    $companyWallet->refresh();
    $devWallet = $paymentService->getWallet($developer);
    
    echo "Pago Procesado con éxito.\n";
    echo "Saldo Empresa (debería haber bajado 500): {$companyWallet->balance}\n";
    echo "Saldo Desarrollador (debería haber subido 400 - 80%): {$devWallet->balance}\n";
    
    // Verify Admin Commission (20% = 100)
    $admin = User::where('role', 'admin')->first();
    if ($admin) {
        echo "Saldo Admin: {$admin->wallet->balance}\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

echo "\n--- Test Finalizado ---\n";
