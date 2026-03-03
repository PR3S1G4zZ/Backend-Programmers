<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\SystemSetting;
use App\Models\UserPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    // --- User Preferences ---

    /**
     * Get authenticated user's preferences.
     */
    public function getPreferences(Request $request)
    {
        $user = $request->user();
        
        // Ensure preferences exist
        if (!$user->preferences) {
            $user->preferences()->create([
                'theme' => 'dark',
                'accent_color' => '#00FF85',
                'language' => 'es'
            ]);
            $user->load('preferences');
        }

        return response()->json([
            'success' => true,
            'preferences' => $user->preferences
        ]);
    }

    /**
     * Update authenticated user's preferences.
     */
    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'theme' => 'sometimes|in:dark,light,terminal',
            'accent_color' => 'sometimes|string|regex:/^#[0-9A-Fa-f]{3,6}$/',
            'language' => 'sometimes|in:es,en',
            'two_factor_enabled' => 'sometimes|boolean'
        ]);

        $user = $request->user();
        
        $preferences = $user->preferences;

        if (!$preferences) {
            $preferences = $user->preferences()->create([
                'theme' => 'dark',
                'accent_color' => '#00FF85',
                'language' => 'es'
            ]);
        }

        $preferences->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Preferencias actualizadas correctamente.',
            'preferences' => $preferences
        ]);
    }

    // --- Admin System Settings ---

    /**
     * Get all system settings (grouped).
     */
    public function getSystemSettings(Request $request)
    {
        // Authorization check (Ensure only admin can access)
        if ($request->user()->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $settings = SystemSetting::all()->groupBy('group');

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Update system settings.
     * Expects an array of settings: [{ key: 'commission_rate', value: '10' }, ...]
     */
    public function updateSystemSettings(Request $request)
    {
        if ($request->user()->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:system_settings,key',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $settingData) {
            SystemSetting::where('key', $settingData['key'])
                ->update(['value' => $settingData['value']]);
        }

        // Log the action
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_system_settings',
            'details' => 'Actualizó configuración del sistema: ' . json_encode($validated['settings']),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Configuración del sistema actualizada.'
        ]);
    }

    /**
     * Get activity logs.
     */
    public function getActivityLogs(Request $request)
    {
        if ($request->user()->user_type !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = min((int)$request->get('per_page', 50), 100);
        $search = $request->get('search', '');

        $query = ActivityLog::with('user:id,name,email');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('details', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'logs' => $logs
        ]);
    }
}
