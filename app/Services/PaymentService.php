<?php

namespace App\Services;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\PlatformCommission;
use Illuminate\Support\Facades\DB;
use Exception;

class PaymentService
{
    /**
     * Calculate commission rate based on amount.
     * < $500: 20%
     * >= $500: 15%
     */
    public function getCommissionRate(float $amount): float
    {
        return $amount < 500 ? 0.20 : 0.15;
    }

    /**
     * Fund a project (Initial 50% deposit or full amount).
     * Moves funds from Company Wallet to Company Held Balance (Escrow).
     */
    public function fundProject(User $company, float $amount, $project)
    {
         $wallet = $this->getWallet($company);
         if ($wallet->balance < $amount) {
             throw new Exception("Saldo insuficiente. Requerido: \${$amount}, Disponible: \${$wallet->balance}");
         }

         DB::transaction(function () use ($wallet, $amount, $project) {
             $wallet->decrement('balance', $amount);
             $wallet->increment('held_balance', $amount);

             $this->createTransaction($wallet, -$amount, 'escrow_deposit', "Depósito en Garantía Proyecto #{$project->id}", $project);
         });
    }

    /**
     * Release funds for a Milestone (or Project Completion).
     * Moves funds from Company Held Balance to Developer Wallet (minus commission).
     *
     * @param bool $updateCommissionRecord If true, updates the PlatformCommission record when called from project completion
     */
    public function releaseMilestone(User $company, float $amount, $project, bool $updateCommissionRecord = false)
    {
        $companyWallet = $this->getWallet($company);
        
        if ($companyWallet->held_balance < $amount) {
            throw new Exception("Fondos en garantía insuficientes. Requerido: \${$amount}, En garantía: \${$companyWallet->held_balance}");
        }

        // 1. Identify all accepted developers
        $acceptedApps = $project->applications()->where('status', 'accepted')->with('developer')->get();
        $developerCount = $acceptedApps->count();

        if ($developerCount === 0) {
           throw new Exception("No hay desarrolladores aceptados para liberar fondos.");
        }

        // 2. Calculate split amount
        $splitAmount = $amount / $developerCount;

        $totalCommissionReleased = 0;

        DB::transaction(function () use ($companyWallet, $acceptedApps, $amount, $splitAmount, $project, &$totalCommissionReleased) {
            // Deduct total from Held Balance
            $companyWallet->decrement('held_balance', $amount);

            // Log release from Company (one transaction for the total)
            $this->createTransaction($companyWallet, 0, 'escrow_release', "Liberación de fondos Proyecto #{$project->id}", $project);

            foreach ($acceptedApps as $app) {
                $developer = $app->developer;
                if (!$developer) continue;

                // Calculate Commission per developer share
                $rate = $this->getCommissionRate($splitAmount);
                $commission = $splitAmount * $rate;
                $netAmount = $splitAmount - $commission;

                $totalCommissionReleased += $commission;

                // Add to Developer
                $devWallet = $this->getWallet($developer);
                $devWallet->increment('balance', $netAmount);

                // Add Commission to Admin
                $adminWallet = $this->getAdminWallet();
                if ($adminWallet) {
                    $adminWallet->increment('balance', $commission);
                    $this->createTransaction($adminWallet, $commission, 'commission', "Comisión Proyecto #{$project->id} (Dev: {$developer->name})", $project);
                }

                // Log deposit to Developer
                $this->createTransaction($devWallet, $netAmount, 'payment_received', "Pago recibido Proyecto #{$project->id}", $project);
            }
        });

        // Update commission record if this is a project completion
        if ($updateCommissionRecord && $totalCommissionReleased > 0) {
            $this->findAndReleaseCommission($project->id, $totalCommissionReleased);
        }
    }

    // --- Legacy / Helper Methods ---

    public function holdFunds(User $company, float $amount, $reference)
    {
        $this->fundProject($company, $amount, $reference);
    }

    public function releaseFunds(User $company, float $amount, $reference)
    {
        $this->releaseMilestone($company, $amount, $reference, true);
    }

    public function processProjectPayment(User $company, User $developer, float $amount, $reference)
    {
        $this->holdFunds($company, $amount, $reference);
        $this->releaseFunds($company, $amount, $reference);
    }

    public function getWallet(User $user): Wallet
    {
        return $user->wallet()->firstOrCreate(['user_id' => $user->id], ['balance' => 0]);
    }

    protected function getAdminWallet()
    {
        $admin = User::where('user_type', 'admin')->first();
        return $admin ? $this->getWallet($admin) : null;
    }

    protected function createTransaction(Wallet $wallet, float $amount, string $type, string $description, $reference)
    {
        $wallet->transactions()->create([
            'amount' => $amount,
            'type' => $type,
            'description' => $description,
            'reference_type' => get_class($reference),
            'reference_id' => $reference->id,
        ]);
    }

    /**
     * Crear un registro de comisión cuando se financia un proyecto
     * Guarda el monto retenido (50%). La comisión se calcula cuando se libera el proyecto.
     * @throws \Exception Si no hay desarrollador asignado al proyecto
     */
    public function createCommissionRecord(User $company, $project, float $totalAmount)
    {
        // Obtener el developer asignado
        $acceptedApp = $project->applications()->where('status', 'accepted')->first();
        
        if (!$acceptedApp) {
            throw new \Exception("No se puede crear registro de comisión sin desarrollador asignado al proyecto #{$project->id}");
        }

        $developerId = $acceptedApp->developer_id;
        $heldAmount = $totalAmount * 0.5; // 50% retenido
        
        return PlatformCommission::create([
            'project_id' => $project->id,
            'company_id' => $company->id,
            'developer_id' => $developerId,
            'total_amount' => $totalAmount,
            'held_amount' => $heldAmount,
            'commission_rate' => $this->getCommissionRate($totalAmount),
            'commission_amount' => 0, // Se calculará cuando se libere el proyecto
            'net_amount' => $totalAmount - $heldAmount,
            'status' => 'pending',
        ]);
    }

    /**
     * Actualizar el registro de comisión cuando se completa el proyecto
     */
    public function releaseCommission($commissionId, float $commissionAmount)
    {
        $commission = PlatformCommission::findOrFail($commissionId);
        $commission->update([
            'commission_amount' => $commissionAmount,
            'status' => 'released',
        ]);
        return $commission;
    }

    /**
     * Buscar y actualizar la comisión del proyecto
     */
    public function findAndReleaseCommission($projectId, float $totalCommissionReleased)
    {
        $commission = PlatformCommission::where('project_id', $projectId)
            ->where('status', 'pending')
            ->first();
        
        if ($commission) {
            $commission->update([
                'commission_amount' => $totalCommissionReleased,
                'status' => 'released',
            ]);
        }
        
        return $commission;
    }

    /**
     * Obtener todas las comisiones de la plataforma
     */
    public function getAllCommissions()
    {
        return PlatformCommission::with(['project', 'company', 'developer'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Obtener el total de comisiones cobradas
     */
    public function getTotalCommission(): float
    {
        return PlatformCommission::where('status', 'released')
            ->sum('commission_amount');
    }

    /**
     * Obtener el total de fondos retenidos
     */
    public function getTotalHeld(): float
    {
        return PlatformCommission::where('status', 'pending')
            ->sum('held_amount');
    }
}
