<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;

class PaymentMethodController extends Controller
{
    public function index(Request $r)
    {
        return $r->user()->paymentMethods;
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'type' => 'required|string|in:credit_card,paypal,bank_transfer,crypto_wallet',
            'details' => 'required|string', // JSON string for simplicity in simulation
            'is_default' => 'boolean'
        ]);

        if ($data['is_default'] ?? false) {
            $r->user()->paymentMethods()->update(['is_default' => false]);
        }

        $method = $r->user()->paymentMethods()->create($data);

        return response()->json(['message' => 'Método de pago agregado.', 'data' => $method], 201);
    }

    public function destroy(Request $r, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->user_id !== $r->user()->id) {
            abort(403);
        }
        $paymentMethod->delete();
        return response()->noContent();
    }
    public function update(Request $r, PaymentMethod $paymentMethod)
    {
        if ($paymentMethod->user_id !== $r->user()->id) {
            abort(403);
        }

        $data = $r->validate([
            'type' => 'string|in:credit_card,paypal,bank_transfer,crypto_wallet',
            'details' => 'string',
            'is_default' => 'boolean'
        ]);

        if (($data['is_default'] ?? false)) {
            $r->user()->paymentMethods()->where('id', '!=', $paymentMethod->id)->update(['is_default' => false]);
        }

        $paymentMethod->update($data);

        return response()->json(['message' => 'Método de pago actualizado.', 'data' => $paymentMethod]);
    }
}
