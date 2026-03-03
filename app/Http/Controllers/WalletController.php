<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            
            Log::info('WalletController@show - Usuario: ' . $user->id . ' | Email: ' . $user->email . ' | Type: ' . ($user->user_type ?? 'N/A'));
            
            // Verificar si ya existe una wallet
            $existingWallet = $user->wallet()->first();
            
            if ($existingWallet) {
                Log::info('WalletController@show - Wallet existente ID: ' . $existingWallet->id);
                $wallet = $existingWallet;
            } else {
                Log::info('WalletController@show - Creando nueva wallet para usuario: ' . $user->id);
                // Crear wallet con valores por defecto igual que PaymentService
                $wallet = $user->wallet()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['balance' => 0]
                );
                Log::info('WalletController@show - Wallet creada/obtenida ID: ' . $wallet->id);
            }
            
            $transactions = $wallet->transactions()->orderBy('created_at', 'desc')->get();
            
            Log::info('WalletController@show - Transacciones obtenidas: ' . $transactions->count());
            Log::info('WalletController@show - Transacciones es tipo: ' . gettype($transactions));
            Log::info('WalletController@show - Transacciones es instancia de: ' . get_class($transactions));
            
            return response()->json([
                'wallet' => $wallet,
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            Log::error('WalletController@show - Error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error al obtener wallet: ' . $e->getMessage()
            ], 500);
        }
    }

    public function recharge(Request $request)
    {
        // For Development/Demo purposes only
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        try {
            DB::beginTransaction();
            
            $wallet = $request->user()->wallet()->firstOrCreate(
                ['user_id' => $request->user()->id],
                ['balance' => 0]
            );
            
            // Incrementar el balance
            $wallet->increment('balance', $request->amount);
            
            // Crear la transacción
            $wallet->transactions()->create([
                'amount' => $request->amount,
                'type' => 'deposit',
                'description' => 'Recarga de prueba',
                'reference_type' => null,
                'reference_id' => null
            ]);

            DB::commit();
            
            Log::info('WalletController@recharge - Recarga exitosa. Wallet ID: ' . $wallet->id . ', Nuevo balance: ' . $wallet->balance);
            
            // Obtener transacciones actualizadas
            $transactions = $wallet->transactions()->orderBy('created_at', 'desc')->get();
            Log::info('WalletController@recharge - Transacciones después de recarga: ' . $transactions->count());

            return response()->json([
                'message' => 'Saldo recargado', 
                'wallet' => $wallet->fresh(),
                'transactions' => $transactions
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en recarga: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error al procesar la recarga: ' . $e->getMessage()
            ], 500);
        }
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $wallet = $request->user()->wallet()->firstOrCreate(['user_id' => $request->user()->id]);
        
        // Verificar que tenga fondos disponibles
        $availableBalance = $wallet->getAvailableBalance();
        if ($availableBalance < $request->amount) {
            return response()->json([
                'message' => 'Fondos insuficientes',
                'available_balance' => $availableBalance
            ], 400);
        }

        // Verificar que tenga un método de pago registrado
        $paymentMethod = $request->user()->paymentMethods()->first();
        if (!$paymentMethod) {
            return response()->json([
                'message' => 'No tienes un método de pago registrado'
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Restar el monto del balance
            $wallet->decrement('balance', $request->amount);

            // Crear la transacción de retiro
            $wallet->transactions()->create([
                'amount' => $request->amount,
                'type' => 'withdraw',
                'description' => 'Retiro a método de pago: ' . $paymentMethod->type,
                'reference_type' => PaymentMethod::class,
                'reference_id' => $paymentMethod->id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Retiro realizado exitosamente',
                'wallet' => $wallet->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en retiro: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al procesar el retiro'
            ], 500);
        }
    }
}
