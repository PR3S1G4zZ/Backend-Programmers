<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\DeveloperProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = $user->user_type === 'company'
            ? CompanyProfile::firstOrCreate(['user_id' => $user->id])
            : DeveloperProfile::firstOrCreate(['user_id' => $user->id]);

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->only('id', 'name', 'lastname', 'email', 'user_type', 'profile_picture'),
                'profile' => $profile,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $userData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'lastname' => 'sometimes|string|max:255',
            'profile_picture' => 'nullable|image|max:2048', // Max 2MB
        ]);

        return DB::transaction(function () use ($request, $user, $userData) {
            if (!empty($userData)) {
                if ($request->hasFile('profile_picture')) {
                    $path = $request->file('profile_picture')->store('profile_pictures', 'public');
                    // Generate full URL
                    $userData['profile_picture'] = url('storage/' . $path);
                }
                $user->update($userData);
            }

            if ($user->user_type === 'company') {
                $data = $request->validate([
                    'company_name' => 'sometimes|string|max:255',
                    'website' => 'nullable|url',
                    'about' => 'nullable|string',
                    'location' => 'nullable|string|max:150',
                    'country' => 'nullable|string|max:100',
                ]);

                $profile = CompanyProfile::firstOrCreate(['user_id' => $user->id]);
                $profile->update($data);
            } else {
                $data = $request->validate([
                    'headline' => 'nullable|string|max:255',
                    'skills' => 'nullable|array',
                    'bio' => 'nullable|string',
                    'links' => 'nullable|array',
                    'location' => 'nullable|string|max:150',
                    'country' => 'nullable|string|max:100',
                    'hourly_rate' => 'nullable|integer|min:0',
                    'availability' => 'nullable|in:available,busy,unavailable',
                    'experience_years' => 'nullable|integer|min:0',
                    'languages' => 'nullable|array',
                ]);

                $profile = DeveloperProfile::firstOrCreate(['user_id' => $user->id]);
                $profile->update($data);
            }

            return response()->json([
                'success' => true,
                'message' => 'Perfil actualizado correctamente.',
                'user' => $user->fresh()->only('id', 'name', 'lastname', 'email', 'user_type', 'profile_picture'),
            ]);
        });
    }
}
