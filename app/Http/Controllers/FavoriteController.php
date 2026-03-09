<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Favorite;
use Illuminate\Support\Facades\DB;

class FavoriteController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if ($user->user_type !== 'company') {
             // Or allow developers to favorite companies? Requirement says 'company favorites developer'
             abort(403, 'Solo empresas pueden tener favoritos');
        }

        // Return list of favorite developer IDs or full objects
        // Assuming we have a relation 'favorites' in User model, or we just query table directly
        // Let's query table directly for simplicity or define relation later
        $favorites = Favorite::where('company_id', $user->id)
            ->pluck('developer_id');

        return response()->json($favorites);
    }

    public function store(Request $request)
    {
        $request->validate([
            'developer_id' => 'required|exists:users,id'
        ]);

        $companyId = $request->user()->id;
        $developerId = $request->developer_id;

        // Verify target is a developer?
        // $dev = User::find($developerId);
        // if ($dev->user_type !== 'developer') ...

        $exists = Favorite::where('company_id', $companyId)
            ->where('developer_id', $developerId)
            ->exists();

        if ($exists) {
            Favorite::where('company_id', $companyId)
                ->where('developer_id', $developerId)
                ->delete();
            return response()->json(['status' => 'removed', 'message' => 'Eliminado de favoritos']);
        } else {
            Favorite::create([
                'company_id' => $companyId,
                'developer_id' => $developerId
            ]);
            return response()->json(['status' => 'added', 'message' => 'Añadido a favoritos']);
        }
    }
}
