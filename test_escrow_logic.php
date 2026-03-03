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

echo "--- Iniciando Test de Lógica de Escrow y Comisión (15%) ---\n";

// 1. Setup Users
$company = User::where('email', 'company_escrow@test.com')->first();
if (!$company) {
    $company = User::factory()->create([
        'name' => 'Company Escrow',
        'lastname' => 'Test',
        'email' => 'company_escrow@test.com',
        'user_type' => 'company',
        'password' => 'Password123!'
    ]);
}
$developer = User::where('email', 'dev_escrow@test.com')->first();
if (!$developer) {
    $developer = User::factory()->create([
        'name' => 'Dev Escrow',
        'lastname' => 'Test',
        'email' => 'dev_escrow@test.com',
        'user_type' => 'programmer',
        'password' => 'Password123!'
    ]);
}
$admin = User::where('role', 'admin')->first();

// 2. Recharge Company Wallet
$paymentService = new PaymentService();
$companyWallet = $paymentService->getWallet($company);
$companyWallet->update(['balance' => 0, 'held_balance' => 0]); // Reset
$companyWallet->increment('balance', 2000);
echo "Saldo Inicial Empresa: {$companyWallet->balance}\n";

// 3. Create Project & Application
$project = Project::create([
    'company_id' => $company->id,
    'title' => 'Proyecto Escrow ' . time(),
    'description' => 'Test de Escrow',
    'budget_min' => 1000,
    'status' => 'open'
]);
$application = Application::create([
    'project_id' => $project->id,
    'developer_id' => $developer->id,
    'status' => 'pending'
]);

// 4. Accept Application (Hold Funds)
echo "\n--- Aceptando Aplicación (Hold) ---\n";
try {
    $paymentService->holdFunds($company, 1000, $project);
    $companyWallet->refresh();
    echo "Saldo Empresa (Balance): {$companyWallet->balance} (Debería ser 1000)\n";
    echo "Saldo Empresa (Held): {$companyWallet->held_balance} (Debería ser 1000)\n";
} catch (Exception $e) {
    echo "ERROR HOLD: " . $e->getMessage() . "\n";
    exit;
}

// 5. Complete Project (Release Funds)
echo "\n--- Completando Proyecto (Release) ---\n";
try {
    $adminBalanceBefore = $admin ? $admin->wallet->balance : 0;
    
    $paymentService->releaseFunds($company, $developer, 1000, $project);
    
    $companyWallet->refresh();
    $devWallet = $paymentService->getWallet($developer);
    $admin->refresh();
    
    echo "Saldo Empresa (Held): {$companyWallet->held_balance} (Debería ser 0)\n";
    echo "Saldo Desarrollador: {$devWallet->balance} (Debería ser 850)\n";
    
    if ($admin) {
        $commission = $admin->wallet->balance - $adminBalanceBefore;
        echo "Comisión Admin Generada: {$commission} (Debería ser 150)\n";
    }

} catch (Exception $e) {
    echo "ERROR RELEASE: " . $e->getMessage() . "\n";
}
echo "\n--- Test Finalizado ---\n";
