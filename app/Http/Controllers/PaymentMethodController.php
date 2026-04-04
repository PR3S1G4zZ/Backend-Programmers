<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;

class PaymentMethodController extends Controller
{
    public function index(Request $r)
    {
        return PaymentMethod::where('user_id', $r->user()->id)
            ->where('is_default', true)
            ->get();
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'type' => 'required|string|in:credit_card,paypal,bank_transfer,crypto_wallet',
            'details' => 'required|string',
            'is_default' => 'nullable'
        ]);

        if (array_key_exists('is_default', $data)) {
            $data['is_default'] = (bool) filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($data['is_default'] ?? false) {
            PaymentMethod::where('user_id', $r->user()->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
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
            'is_default' => 'nullable'
        ]);

        if (array_key_exists('is_default', $data)) {
            $data['is_default'] = (bool) filter_var($data['is_default'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        if ($data['is_default'] ?? false) {
            PaymentMethod::where('user_id', $r->user()->id)
                ->where('id', '!=', $paymentMethod->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
        }

        $paymentMethod->update($data);

        return response()->json(['message' => 'Método de pago actualizado.', 'data' => $paymentMethod]);
    }
}
