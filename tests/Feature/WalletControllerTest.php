<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WalletControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test que verifica que el endpoint show() devuelve la wallet y las transacciones del usuario
     */
    public function testShowWallet(): void
    {
        // Crear usuario con wallet y transacciones
        $user = User::factory()->create();
        
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 500.00,
            'held_balance' => 0,
        ]);

        // Crear transacciones asociadas a la wallet
        Transaction::factory()->count(3)->create([
            'wallet_id' => $wallet->id,
            'type' => 'deposit',
            'amount' => 100.00,
            'description' => 'Recarga de prueba',
        ]);

        // Autenticar al usuario
        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/wallet');

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJsonStructure([
                'wallet' => [
                    'id',
                    'user_id',
                    'balance',
                    'held_balance',
                ],
                'transactions' => [
                    'data' => [
                        '*' => [
                            'id',
                            'wallet_id',
                            'amount',
                            'type',
                            'description',
                        ]
                    ]
                ]
            ]);

        // Verificar que el balance es correcto
        $this->assertEquals(500.00, $response->json('wallet.balance'));
        $this->assertCount(3, $response->json('transactions.data'));
    }

    /**
     * Test que verifica que un usuario puede recargar su wallet
     */
    public function testRechargeWallet(): void
    {
        // Crear usuario
        $user = User::factory()->create();

        // Crear wallet con balance inicial
        Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100.00,
            'held_balance' => 0,
        ]);

        // Autenticar y hacer la recarga
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/recharge', [
                'amount' => 250.00,
            ]);

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Saldo recargado',
            ]);

        // Verificar que el balance se actualizó correctamente
        $wallet = $user->wallet->fresh();
        $this->assertEquals(350.00, $wallet->balance);

        // Verificar que se creó la transacción
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'amount' => 250.00,
            'type' => 'deposit',
            'description' => 'Recarga de prueba',
        ]);
    }

    /**
     * Test que verifica que un usuario puede retirar fondos
     */
    public function testWithdrawFunds(): void
    {
        // Crear usuario
        $user = User::factory()->create();

        // Crear wallet con balance suficiente
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 500.00,
            'held_balance' => 0,
        ]);

        // Crear método de pago para el usuario
        PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'bank_account',
            'provider' => 'Visa',
            'account_last_four' => '1234',
        ]);

        // Autenticar y hacer el retiro
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/withdraw', [
                'amount' => 200.00,
            ]);

        // Verificar respuesta exitosa
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Retiro realizado exitosamente',
            ]);

        // Verificar que el balance se actualizó correctamente
        $wallet->refresh();
        $this->assertEquals(300.00, $wallet->balance);

        // Verificar que se creó la transacción de retiro
        $this->assertDatabaseHas('transactions', [
            'wallet_id' => $wallet->id,
            'amount' => 200.00,
            'type' => 'withdraw',
        ]);
    }

    /**
     * Test que verifica que no se puede retirar más de lo disponible
     */
    public function testWithdrawWithInsufficientFunds(): void
    {
        // Crear usuario
        $user = User::factory()->create();

        // Crear wallet con balance insuficiente
        $wallet = Wallet::factory()->create([
            'user_id' => $user->id,
            'balance' => 100.00,
            'held_balance' => 0,
        ]);

        // Crear método de pago para el usuario
        PaymentMethod::factory()->create([
            'user_id' => $user->id,
            'type' => 'bank_account',
            'provider' => 'Visa',
            'account_last_four' => '1234',
        ]);

        // Autenticar e intentar retirar más de lo disponible
        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/wallet/withdraw', [
                'amount' => 500.00,
            ]);

        // Verificar que retornó error 400
        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Fondos insuficientes',
            ]);

        // Verificar que el balance no cambió
        $wallet->refresh();
        $this->assertEquals(100.00, $wallet->balance);

        // Verificar que NO se creó ninguna transacción de retiro
        $this->assertDatabaseMissing('transactions', [
            'wallet_id' => $wallet->id,
            'type' => 'withdraw',
            'amount' => 500.00,
        ]);
    }
}
