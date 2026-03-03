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

echo "--- Iniciando Test de Milestones y Depósito del 50% ---\n";

$paymentService = new PaymentService();

// 1. Setup Users
$company = User::where('email', 'company_milestone@test.com')->first();
if (!$company) {
    $company = User::factory()->create([
        'name' => 'Company Milestone',
        'lastname' => 'Test',
        'email' => 'company_milestone@test.com',
        'user_type' => 'company',
        'password' => 'Password123!'
    ]);
}
$developer = User::where('email', 'dev_milestone@test.com')->first();
if (!$developer) {
    $developer = User::factory()->create([
        'name' => 'Dev Milestone',
        'lastname' => 'Test',
        'email' => 'dev_milestone@test.com',
        'user_type' => 'programmer',
        'password' => 'Password123!'
    ]);
}

// 2. Recharge Company Wallet
$companyWallet = $paymentService->getWallet($company);
$companyWallet->update(['balance' => 0, 'held_balance' => 0]); 
$companyWallet->increment('balance', 5000);
echo "Saldo Inicial Empresa: {$companyWallet->balance}\n";

// 3. Create Project
$project = Project::create([
    'company_id' => $company->id,
    'title' => 'Proyecto Milestone ' . time(),
    'description' => 'Test de Milestones',
    'budget_min' => 1000, 
    'status' => 'pending_payment'
]);
echo "Proyecto Creado (Estado): {$project->status}\n";

// 4. Fund Project (50% Deposit)
echo "\n--- Financiando Proyecto (50% = 500) ---\n";
try {
    $depositAmount = $project->budget_min * 0.5;
    $paymentService->fundProject($company, $depositAmount, $project);
    
    $project->refresh();
    $companyWallet->refresh();
    
    echo "Saldo Empresa (Balance): {$companyWallet->balance} (Debería ser 4500)\n";
    echo "Saldo Empresa (Held): {$companyWallet->held_balance} (Debería ser 500)\n";
    // echo "Proyecto Estado: {$project->status}\n"; // Controller would update this, here we just test service logic
    
} catch (Exception $e) {
    echo "ERROR FUNDING: " . $e->getMessage() . "\n";
    exit;
}

// 5. Release Milestone (Release the 500 held)
echo "\n--- Liberando Milestone (500) ---\n";
try {
    // 500 is > 500? No, is equal. Rate should be 15%? 
    // Logic: < 500 is 20%. >= 500 is 15%.
    // 500 * 0.15 = 75 commission. Net = 425.
    
    $paymentService->releaseMilestone($company, $developer, 500, $project);
    
    $companyWallet->refresh();
    $devWallet = $paymentService->getWallet($developer);
    
    echo "Saldo Empresa (Held): {$companyWallet->held_balance} (Debería ser 0)\n";
    echo "Saldo Desarrollador: {$devWallet->balance} (Debería ser 425)\n";

} catch (Exception $e) {
    echo "ERROR RELEASING: " . $e->getMessage() . "\n";
}

echo "\n--- Test Finalizado ---\n";
