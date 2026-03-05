<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Project;
use App\Models\Application;
use App\Models\Message;
use App\Models\Review;
use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\DeveloperProfile;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    /**
     * Crear un usuario (solo para administradores)
     */
    public function createUser(Request $request): JsonResponse
    {
        try {

            $validated = $request->validate([
                'name' => 'required|string|max:255|regex:/^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/',
                'lastname' => 'required|string|max:255|regex:/^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/',
                'email' => 'required|string|email|max:255|unique:users,email',
                'user_type' => 'required|in:programmer,company,admin',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:15',
                    'regex:/^\S+$/',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,15}$/'
                ],
            ]);

            $user = User::create([
                'name' => strip_tags(trim($validated['name'])),
                'lastname' => strip_tags(trim($validated['lastname'])),
                'email' => strtolower(trim($validated['email'])),
                'password' => Hash::make($validated['password']),
                'user_type' => $validated['user_type'],
                'role' => $validated['user_type'],
            ]);

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'create_user',
                'details' => 'Creó usuario: ' . $user->email . ' (tipo: ' . $user->user_type . ')',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario creado exitosamente.',
                'user' => $user->only(['id', 'name', 'lastname', 'email', 'user_type', 'created_at', 'email_verified_at', 'banned_at']),
            ], 201);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear usuario.'
            ], 500);
        }
    }

    /**
     * Obtener todos los usuarios (solo para administradores)
     */
    public function getUsers(Request $request): JsonResponse
    {
        try {

            // Obtener usuarios con paginación opcional
            $perPage = min((int)$request->get('per_page', 25), 100);
            $search = $request->get('search', '');
            $userType = $request->get('user_type', '');

            $query = User::select('id', 'name', 'lastname', 'email', 'user_type', 'created_at', 'email_verified_at');

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('lastname', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Apply user_type filter
            if ($userType && in_array($userType, ['admin', 'company', 'programmer'])) {
                $query->where('user_type', $userType);
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total()
                ]
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuarios.'
            ], 500);
        }
    }

    /**
     * Obtener un usuario específico
     */
    public function getUser($id): JsonResponse
    {
        try {

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'user' => $user
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener usuario.'
            ], 500);
        }
    }

    /**
     * Actualizar un usuario
     */
    public function updateUser(Request $request, $id): JsonResponse
    {
        try {

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            // Validar datos
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255|regex:/^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/',
                'lastname' => 'sometimes|string|max:255|regex:/^(?!\s)[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+(?<!\s)$/',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
                'user_type' => 'sometimes|in:programmer,company,admin'
            ]);

            if (isset($validated['user_type'])) {
                $validated['role'] = $validated['user_type'];
            }

            $user->update($validated);

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'update_user',
                'details' => 'Actualizó usuario: ' . $user->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente.',
                'user' => $user->only(['id', 'name', 'lastname', 'email', 'user_type', 'created_at', 'email_verified_at', 'banned_at'])
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar usuario.'
            ], 500);
        }
    }

    /**
     * Banear o desbanear un usuario
     */
    public function banUser($id): JsonResponse
    {
        try {

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            } 
            

            // No se puede banear a un admin
            if ($user->user_type === 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes banear a un administrador.'
                ], 403);
            }

            $banned = false;

            if (is_null($user->banned_at)) {
                // Banear
                $user->banned_at = now();
                $banned = true;
            } else {
                // Desbanear
                $user->banned_at = null;
                $banned = false;
            }

            $user->save();

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => $banned ? 'ban_user' : 'unban_user',
                'details' => ($banned ? 'Baneó' : 'Desbaneó') . ' usuario: ' . $user->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => $banned ? 'Usuario baneado exitosamente.' : 'Usuario desbaneado exitosamente.',
                'banned'  => $banned,
                'user'    => $user->only(['id', 'name', 'lastname', 'email', 'user_type', 'created_at', 'email_verified_at', 'banned_at'])
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al banear/desbanear usuario.'
            ], 500);
        }
    }

    /**
     * Eliminar un usuario
     */
    public function deleteUser($id): JsonResponse
    {
        try {

            $user = User::find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            // No permitir que un admin se elimine a sí mismo
            if ($user->id === Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No puedes eliminar tu propia cuenta.'
                ], 400);
            }

            $user->delete();

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_user',
                'details' => 'Eliminó usuario: ' . $user->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente.'
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar usuario.'
            ], 500);
        }
    }

    /**
     * Restaurar un usuario eliminado (soft delete)
     */
    public function restoreUser($id): JsonResponse
    {
        try {
            $user = User::withTrashed()->find($id);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado.'
                ], 404);
            }

            if (!$user->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no está eliminado.'
                ], 400);
            }

            $user->restore();

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'restore_user',
                'details' => 'Restauró usuario: ' . $user->email,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Usuario restaurado exitosamente.',
                'user' => $user
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al restaurar usuario.'
            ], 500);
        }
    }

    /**
     * Obtener todos los proyectos (incluyendo eliminados)
     */
    public function getProjects(Request $request): JsonResponse
    {
        try {

            $perPage = $request->get('per_page', 20);
            $query = Project::withTrashed()->with(['company:id,name,email', 'categories:id,name']);

            if ($request->filled('status')) {
                if ($request->status === 'deleted') {
                    $query->onlyTrashed();
                } else {
                    $query->where('status', $request->status);
                }
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $projects = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'projects' => $projects->items(),
                'pagination' => [
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'per_page' => $projects->perPage(),
                    'total' => $projects->total()
                ]
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Error al obtener proyectos.'], 500);
        }
    }

    /**
     * Actualizar un proyecto
     */
    public function updateProject(Request $request, $id): JsonResponse
    {
        try {

            $project = Project::withTrashed()->find($id);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
            }

            $validated = $request->validate([
                'title' => 'sometimes|string|max:150',
                'description' => 'sometimes|string',
                'status' => 'sometimes|in:open,in_progress,completed,cancelled,draft',
                'budget_min' => 'nullable|numeric',
                'budget_max' => 'nullable|numeric',
                'deadline' => 'nullable|date',
            ]);

            $project->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Proyecto actualizado exitosamente.',
                'project' => $project
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Error al actualizar proyecto.'], 500);
        }
    }

    /**
     * Eliminar (Soft Delete) un proyecto
     */
    public function deleteProject($id): JsonResponse
    {
        try {

            $project = Project::find($id);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Proyecto no encontrado o ya eliminado.'], 404);
            }

            $project->delete();

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'delete_project',
                'details' => 'Eliminó proyecto: ' . $project->title . ' (ID: ' . $project->id . ')',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proyecto eliminado (soft delete) exitosamente.',
                'deleted_at' => now()
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Error al eliminar proyecto.'], 500);
        }
    }

    /**
     * Restaurar un proyecto eliminado
     */
    public function restoreProject($id): JsonResponse
    {
        try {

            $project = Project::withTrashed()->find($id);

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Proyecto no encontrado.'], 404);
            }

            if (!$project->trashed()) {
                 return response()->json(['success' => false, 'message' => 'El proyecto no está eliminado.'], 400);
            }

            $project->restore();

            // Audit log
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => 'restore_project',
                'details' => 'Restauró proyecto: ' . $project->title . ' (ID: ' . $project->id . ')',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proyecto restaurado exitosamente.',
                'project' => $project
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json(['success' => false, 'message' => 'Error al restaurar proyecto.'], 500);
        }
    }

    /**
     * Obtener métricas agregadas para dashboards administrativos
     */
    public function metrics(Request $request): JsonResponse
    {
        try {

            $period = $this->sanitizePeriod($request->get('period', 'month'));
            $timeSeries = $this->buildTimeSeries($period);

            return response()->json([
                'success' => true,
                'data' => [
                    'activity' => $this->buildActivityMetrics($period, $timeSeries),
                    'financial' => $this->buildFinancialMetrics($period, $timeSeries),
                    'growth' => $this->buildGrowthMetrics($period, $timeSeries),
                    'projects' => $this->buildProjectsMetrics($period),
                    'satisfaction' => $this->buildSatisfactionMetrics($period),
                ],
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener métricas.'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de usuarios
     */
    public function getUserStats(): JsonResponse
    {
        try {

            $stats = [
                'total_users' => User::count(),
                'admins' => User::where('user_type', 'admin')->count(),
                'companies' => User::where('user_type', 'company')->count(),
                'programmers' => User::where('user_type', 'programmer')->count(),
                'verified_emails' => User::whereNotNull('email_verified_at')->count(),
                'unverified_emails' => User::whereNull('email_verified_at')->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count()
            ];

            return response()->json([
                'success' => true,
                'stats' => $stats
            ]);

        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas.'
            ], 500);
        }
    }

    private function sanitizePeriod(?string $period): string
    {
        $allowed = ['day', 'week', 'month', 'year'];
        return in_array($period, $allowed, true) ? $period : 'month';
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'day' => 'día anterior',
            'week' => 'semana anterior',
            'year' => 'año anterior',
            default => 'mes anterior',
        };
    }

    private function periodRange(string $period, int $offset = 0): array
    {
        $now = Carbon::now();
        return match ($period) {
            'day' => [$now->copy()->subHours(24 * ($offset + 1)), $now->copy()->subHours(24 * $offset)],
            'week' => [$now->copy()->subDays(7 * ($offset + 1)), $now->copy()->subDays(7 * $offset)],
            'year' => [$now->copy()->subDays(365 * ($offset + 1)), $now->copy()->subDays(365 * $offset)],
            default => [$now->copy()->subDays(30 * ($offset + 1)), $now->copy()->subDays(30 * $offset)],
        };
    }

    private function buildChange(float $current, float $previous, string $period): array
    {
        $delta = $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);
        return [
            'value' => round($delta, 1),
            'isPositive' => $current >= $previous,
            'period' => $this->periodLabel($period),
        ];
    }

    private function buildTimeSeries(string $period): array
    {
        $monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $now = Carbon::now();
        $series = [];

        if ($period === 'day') {
            for ($i = 23; $i >= 0; $i--) {
                $start = $now->copy()->subHours($i + 1);
                $end = $now->copy()->subHours($i);
                $series[] = $this->bucketCounts($start, $end, $start->format('H:00'));
            }
            return $series;
        }

        if ($period === 'week') {
            for ($i = 6; $i >= 0; $i--) {
                $start = $now->copy()->subDays($i + 1)->startOfDay();
                $end = $now->copy()->subDays($i)->startOfDay();
                $dayIndex = (int) $start->format('N') - 1;
                $series[] = $this->bucketCounts($start, $end, $dayLabels[$dayIndex] ?? $start->format('D'));
            }
            return $series;
        }

        if ($period === 'year') {
            for ($i = 4; $i >= 0; $i--) {
                $year = $now->copy()->subYears($i)->year;
                $start = Carbon::create($year, 1, 1)->startOfYear();
                $end = Carbon::create($year, 12, 31)->endOfYear();
                $series[] = $this->bucketCounts($start, $end, (string) $year);
            }
            return $series;
        }

        for ($i = 11; $i >= 0; $i--) {
            $date = $now->copy()->subMonths($i);
            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();
            $series[] = $this->bucketCounts($start, $end, $monthLabels[$date->month - 1]);
        }

        return $series;
    }

    private function bucketCounts(Carbon $start, Carbon $end, string $label): array
    {
        $users = User::whereBetween('created_at', [$start, $end])->count();
        $programmers = User::where('user_type', 'programmer')->whereBetween('created_at', [$start, $end])->count();
        $companies = User::where('user_type', 'company')->whereBetween('created_at', [$start, $end])->count();
        $projects = Project::whereBetween('created_at', [$start, $end])->count();
        $applications = Application::whereBetween('created_at', [$start, $end])->count();
        $revenue = Project::whereBetween('created_at', [$start, $end])
            ->get()
            ->sum(function($project) {
                return $project->budget_max ?? $project->budget_min ?? 0;
            });

        return [
            'period' => $label,
            'users' => $users,
            'programmers' => $programmers,
            'companies' => $companies,
            'projects' => $projects,
            'applications' => $applications,
            'revenue' => $revenue,
        ];
    }

    private function buildActivityMetrics(string $period, array $timeSeries): array
    {
        [$start, $end] = $this->periodRange($period, 0);
        [$prevStart, $prevEnd] = $this->periodRange($period, 1);

        $messages = Message::whereBetween('created_at', [$start, $end])->count();
        $messagesPrev = Message::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $applications = Application::whereBetween('created_at', [$start, $end])->count();
        $applicationsPrev = Application::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $users = User::count();
        $sessions = $users > 0 ? round(($messages + $applications) / $users, 1) : 0;
        $sessionsPrev = $users > 0 ? round(($messagesPrev + $applicationsPrev) / $users, 1) : 0;

        $avgSessionTime = $users > 0 ? min(60, round(5 + ($messages / max(1, $users)) * 2)) : 0;
        $avgSessionTimePrev = $users > 0 ? min(60, round(5 + ($messagesPrev / max(1, $users)) * 2)) : 0;

        $activeDevelopers = Application::distinct('developer_id')->count('developer_id');
        $totalDevelopers = User::where('user_type', 'programmer')->count();
        $engagementScore = $totalDevelopers > 0 ? round(($activeDevelopers / $totalDevelopers) * 100) : 0;
        $activityHeatmap = $this->buildActivityHeatmap();
        $peakHours = $this->buildPeakHours();
        $userEngagement = $this->buildUserEngagement($start, $end);
        $activityTrends = $this->buildActivityTrends($start, $end, $prevStart, $prevEnd);

        return [
            'kpis' => [
                [
                    'title' => 'Sesiones Promedio',
                    'value' => $sessions,
                    'change' => $this->buildChange($sessions, $sessionsPrev, $period),
                    'description' => 'Por usuario',
                ],
                [
                    'title' => 'Mensajes Enviados',
                    'value' => $messages,
                    'change' => $this->buildChange($messages, $messagesPrev, $period),
                    'description' => 'En el período',
                ],
                [
                    'title' => 'Archivos Compartidos',
                    'value' => $applications,
                    'change' => $this->buildChange($applications, $applicationsPrev, $period),
                    'description' => 'Aplicaciones registradas',
                ],
                [
                    'title' => 'Tiempo Promedio Sesión',
                    'value' => $avgSessionTime . ' min',
                    'change' => $this->buildChange($avgSessionTime, $avgSessionTimePrev, $period),
                    'description' => 'Estimado por actividad',
                ],
            ],
            'timeSeries' => $timeSeries,
            'engagementScore' => $engagementScore,
            'activityHeatmap' => $activityHeatmap,
            'peakHours' => $peakHours,
            'userEngagement' => $userEngagement,
            'activityTrends' => $activityTrends,
        ];
    }

    private function buildFinancialMetrics(string $period, array $timeSeries): array
    {
        [$start, $end] = $this->periodRange($period, 0);
        [$prevStart, $prevEnd] = $this->periodRange($period, 1);

        $revenue = Project::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->sum(function($project) {
                return $project->budget_max ?? $project->budget_min ?? 0;
            });
        $revenuePrev = Project::where('status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->get()
            ->sum(function($project) {
                return $project->budget_max ?? $project->budget_min ?? 0;
            });

        $gmv = Project::whereBetween('created_at', [$start, $end])
            ->get()
            ->sum(function($project) {
                return $project->budget_max ?? $project->budget_min ?? 0;
            });
        $gmvPrev = Project::whereBetween('created_at', [$prevStart, $prevEnd])
            ->get()
            ->sum(function($project) {
                return $project->budget_max ?? $project->budget_min ?? 0;
            });

        $transactions = Application::whereBetween('created_at', [$start, $end])->count();
        $transactionsPrev = Application::whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $avgTicket = $transactions > 0 ? round($revenue / $transactions, 2) : 0;
        $avgTicketPrev = $transactionsPrev > 0 ? round($revenuePrev / $transactionsPrev, 2) : 0;

        $statusTotals = Project::whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy('status')
            ->map->count();

        $totalProjects = $statusTotals->sum() ?: 1;
        $revenueSources = collect($statusTotals)->map(function ($count, $status) use ($totalProjects) {
            return [
                'name' => match ($status) {
                    'open' => 'Proyectos abiertos',
                    'in_progress' => 'Proyectos en progreso',
                    'completed' => 'Proyectos completados',
                    'cancelled' => 'Proyectos cancelados',
                    default => 'Otros',
                },
                'value' => round(($count / $totalProjects) * 100, 1),
                'amount' => $count,
                'color' => match ($status) {
                    'open' => 'var(--color-neon-green)',
                    'in_progress' => 'var(--color-emerald-green)',
                    'completed' => 'var(--color-chart-3)',
                    'cancelled' => 'var(--color-chart-4)',
                    default => 'var(--color-neon-green)',
                },
            ];
        })->values();

        $recentTransactions = Application::with(['project.company', 'developer'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($application) {
                $project = $application->project;
                $amount = $project ? ($project->budget_max ?? $project->budget_min ?? 0) : 0;
                $status = match ($application->status) {
                    'accepted' => 'Completado',
                    'reviewed' => 'Pendiente',
                    'rejected' => 'Fallido',
                    default => 'Pendiente',
                };

                return [
                    'id' => 'APP-' . $application->id,
                    'type' => 'Comisión',
                    'description' => $project?->title ?? 'Proyecto',
                    'amount' => $amount,
                    'client' => $project?->company?->name ?? 'Cliente',
                    'date' => $application->created_at?->toDateString(),
                    'status' => $status,
                ];
            });

        return [
            'kpis' => [
                [
                    'title' => 'Ingresos Netos',
                    'value' => '$' . number_format($revenue, 0, '.', ','),
                    'change' => $this->buildChange($revenue, $revenuePrev, $period),
                    'description' => 'Ingresos en el período',
                ],
                [
                    'title' => 'GMV Total',
                    'value' => '$' . number_format($gmv, 0, '.', ','),
                    'change' => $this->buildChange($gmv, $gmvPrev, $period),
                    'description' => 'Valor bruto de proyectos',
                ],
                [
                    'title' => 'Transacciones',
                    'value' => $transactions,
                    'change' => $this->buildChange($transactions, $transactionsPrev, $period),
                    'description' => 'Aplicaciones registradas',
                ],
                [
                    'title' => 'Ticket Promedio',
                    'value' => '$' . number_format($avgTicket, 0, '.', ','),
                    'change' => $this->buildChange($avgTicket, $avgTicketPrev, $period),
                    'description' => 'Por aplicación',
                ],
            ],
            'timeSeries' => $timeSeries,
            'revenueSources' => $revenueSources,
            'recentTransactions' => $recentTransactions,
        ];
    }

    private function buildGrowthMetrics(string $period, array $timeSeries): array
    {
        [$start, $end] = $this->periodRange($period, 0);
        [$prevStart, $prevEnd] = $this->periodRange($period, 1);

        $newFreelancers = User::where('user_type', 'programmer')->whereBetween('created_at', [$start, $end])->count();
        $newFreelancersPrev = User::where('user_type', 'programmer')->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $newClients = User::where('user_type', 'company')->whereBetween('created_at', [$start, $end])->count();
        $newClientsPrev = User::where('user_type', 'company')->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $applications = Application::whereBetween('created_at', [$start, $end])->count();
        $accepted = Application::where('status', 'accepted')->whereBetween('created_at', [$start, $end])->count();
        $applicationsPrev = Application::whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $acceptedPrev = Application::where('status', 'accepted')->whereBetween('created_at', [$prevStart, $prevEnd])->count();

        $conversionRate = $applications > 0 ? round(($accepted / $applications) * 100, 1) : 0;
        $conversionRatePrev = $applicationsPrev > 0 ? round(($acceptedPrev / $applicationsPrev) * 100, 1) : 0;

        $totalDevelopers = User::where('user_type', 'programmer')->count();
        $activeDevelopers = Application::distinct('developer_id')->count('developer_id');
        $retention = $totalDevelopers > 0 ? round(($activeDevelopers / $totalDevelopers) * 100, 1) : 0;

        $funnel = [
            ['label' => 'Registros', 'value' => $newFreelancers + $newClients],
            ['label' => 'Proyectos', 'value' => Project::whereBetween('created_at', [$start, $end])->count()],
            ['label' => 'Aplicaciones', 'value' => $applications],
            ['label' => 'Aceptadas', 'value' => $accepted],
        ];

        return [
            'kpis' => [
                [
                    'title' => 'Nuevos Freelancers',
                    'value' => $newFreelancers,
                    'change' => $this->buildChange($newFreelancers, $newFreelancersPrev, $period),
                ],
                [
                    'title' => 'Nuevos Clientes',
                    'value' => $newClients,
                    'change' => $this->buildChange($newClients, $newClientsPrev, $period),
                ],
                [
                    'title' => 'Tasa de Conversión',
                    'value' => $conversionRate . '%',
                    'change' => $this->buildChange($conversionRate, $conversionRatePrev, $period),
                ],
                [
                    'title' => 'Retención 30 días',
                    'value' => $retention . '%',
                    'change' => $this->buildChange($retention, $retention, $period),
                ],
            ],
            'timeSeries' => $timeSeries,
            'funnel' => $funnel,
            'geographicData' => $this->buildGeographicData(),
            'retention' => $this->buildRetentionData($period),
        ];
    }

    private function buildProjectsMetrics(string $period): array
    {
        [$start, $end] = $this->periodRange($period, 0);
        [$prevStart, $prevEnd] = $this->periodRange($period, 1);

        $activeProjects = Project::whereIn('status', ['open', 'in_progress'])->count();
        $activeProjectsPrev = Project::whereIn('status', ['open', 'in_progress'])
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $completedProjects = Project::where('status', 'completed')->count();
        $completedProjectsPrev = Project::where('status', 'completed')
            ->whereBetween('created_at', [$prevStart, $prevEnd])
            ->count();

        $avgDuration = Project::where('status', 'completed')
            ->get()
            ->avg(function($project) {
                return $project->created_at && $project->updated_at 
                    ? $project->created_at->diffInDays($project->updated_at) 
                    : 0;
            });
        $avgDuration = $avgDuration ? round($avgDuration) : 0;

        $applications = Application::count();
        $accepted = Application::where('status', 'accepted')->count();
        $successRate = $applications > 0 ? round(($accepted / $applications) * 100, 1) : 0;

        $statusCounts = Project::get()
            ->groupBy('status')
            ->map->count();
        $totalStatus = $statusCounts->sum() ?: 1;
        $categories = collect($statusCounts)->map(function ($count, $status) use ($totalStatus) {
            return [
                'category' => match ($status) {
                    'open' => 'Abiertos',
                    'in_progress' => 'En progreso',
                    'completed' => 'Completados',
                    'cancelled' => 'Cancelados',
                    default => 'Otros',
                },
                'projects' => $count,
                'percentage' => round(($count / $totalStatus) * 100, 1),
            ];
        })->values();

        $projectsWithApplications = Project::whereHas('applications')->count();
        $funnel = [
            ['label' => 'Publicados', 'value' => Project::count()],
            ['label' => 'Con propuestas', 'value' => $projectsWithApplications],
            ['label' => 'En progreso', 'value' => Project::where('status', 'in_progress')->count()],
            ['label' => 'Completados', 'value' => $completedProjects],
        ];

        return [
            'kpis' => [
                [
                    'title' => 'Proyectos Activos',
                    'value' => $activeProjects,
                    'change' => $this->buildChange($activeProjects, $activeProjectsPrev, $period),
                ],
                [
                    'title' => 'Proyectos Completados',
                    'value' => $completedProjects,
                    'change' => $this->buildChange($completedProjects, $completedProjectsPrev, $period),
                ],
                [
                    'title' => 'Tiempo Promedio',
                    'value' => $avgDuration . ' días',
                    'change' => $this->buildChange($avgDuration, $avgDuration, $period),
                ],
                [
                    'title' => 'Tasa de Éxito',
                    'value' => $successRate . '%',
                    'change' => $this->buildChange($successRate, $successRate, $period),
                ],
            ],
            'categories' => $categories,
            'funnel' => $funnel,
        ];
    }


    private function buildSatisfactionMetrics(string $period): array
    {
        [$start, $end] = $this->periodRange($period, 0);
        [$prevStart, $prevEnd] = $this->periodRange($period, 1);

        $reviews = Review::whereBetween('created_at', [$start, $end]);
        $reviewsPrev = Review::whereBetween('created_at', [$prevStart, $prevEnd]);

        $reviewCount = $reviews->count();
        $reviewCountPrev = $reviewsPrev->count();

        $avgRating = $reviewCount > 0 ? round($reviews->avg('rating'), 1) : 0;
        $avgRatingPrev = $reviewCountPrev > 0 ? round($reviewsPrev->avg('rating'), 1) : 0;

        $ratingBuckets = Review::whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy('rating')
            ->map->count();

        $ratingData = collect(range(1, 5))->map(function ($rating) use ($ratingBuckets, $reviewCount) {
            $count = $ratingBuckets[$rating] ?? 0;
            $percentage = $reviewCount > 0 ? round(($count / $reviewCount) * 100, 1) : 0;
            return [
                'rating' => (string) $rating,
                'count' => $count,
                'percentage' => $percentage,
            ];
        });

        $positiveReviews = Review::whereBetween('created_at', [$start, $end])
            ->where('rating', '>=', 4)
            ->count();
        $onTime = $reviewCount > 0 ? round(($positiveReviews / $reviewCount) * 100, 1) : 0;
        $satisfaction = $onTime;
        $feedback = $onTime;

        $promoters = Review::whereBetween('created_at', [$start, $end])->where('rating', 5)->count();
        $detractors = Review::whereBetween('created_at', [$start, $end])->where('rating', '<=', 2)->count();
        $nps = $reviewCount > 0 ? round((($promoters - $detractors) / $reviewCount) * 100) : 0;
        $csat = $reviewCount > 0 ? round(($positiveReviews / $reviewCount) * 100) : 0;

        $recentFeedback = Review::with(['project', 'company', 'developer'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'client' => $review->company?->name ?? 'Cliente',
                    'freelancer' => $review->developer?->name ?? 'Desarrollador',
                    'project' => $review->project?->title ?? 'Proyecto',
                    'rating' => $review->rating,
                    'comment' => $review->comment ?? '',
                    'date' => $review->created_at?->toDateString(),
                    'avatar' => null,
                ];
            });

        $topProjects = Review::with('project.categories')
            ->get()
            ->groupBy('project_id')
            ->map(function($reviews) {
                $project = $reviews->first()->project;
                $category = $project?->categories?->first()?->name ?? 'Sin categoría';
                
                return [
                    'project' => $project?->title ?? 'Proyecto',
                    'rating' => round($reviews->avg('rating'), 1),
                    'reviews' => $reviews->count(),
                    'category' => $category,
                ];
            })
            ->sortByDesc('rating')
            ->take(5)
            ->values();

        $qualityMetrics = [
            ['metric' => 'Código Limpio', 'score' => $satisfaction, 'icon' => '💻'],
            ['metric' => 'Comunicación', 'score' => max(0, $satisfaction - 4), 'icon' => '💬'],
            ['metric' => 'Cumplimiento', 'score' => $onTime, 'icon' => '⏰'],
            ['metric' => 'Creatividad', 'score' => max(0, $satisfaction - 8), 'icon' => '🎨'],
            ['metric' => 'Soporte Post-Entrega', 'score' => max(0, $satisfaction - 12), 'icon' => '🔧'],
        ];

        return [
            'kpis' => [
                [
                    'title' => 'Rating Promedio',
                    'value' => $avgRating,
                    'change' => $this->buildChange($avgRating, $avgRatingPrev, $period),
                    'description' => 'Calificación general',
                ],
                [
                    'title' => 'Proyectos a Tiempo',
                    'value' => $onTime . '%',
                    'change' => $this->buildChange($onTime, $onTime, $period),
                    'description' => 'Entregados puntualmente',
                ],
                [
                    'title' => 'Satisfacción Cliente',
                    'value' => $satisfaction . '%',
                    'change' => $this->buildChange($satisfaction, $satisfaction, $period),
                    'description' => 'CSAT promedio',
                ],
                [
                    'title' => 'Feedback Positivo',
                    'value' => $feedback . '%',
                    'change' => $this->buildChange($feedback, $feedback, $period),
                    'description' => '4-5 estrellas',
                ],
            ],
            'ratingData' => $ratingData,
            'recentFeedback' => $recentFeedback,
            'qualityMetrics' => $qualityMetrics,
            'topRatedProjects' => $topProjects,
            'nps' => $nps,
            'csat' => $csat,
        ];
    }

    private function buildActivityHeatmap(): array
    {
        $dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
        $heatmap = [];

        foreach ($dayLabels as $label) {
            $heatmap[] = ['day' => $label, 'hours' => array_fill(0, 24, 0)];
        }

        $start = Carbon::now()->subDays(30);
        $end = Carbon::now();

        $activitySources = [
            Message::whereBetween('created_at', [$start, $end])->get(['created_at']),
            Application::whereBetween('created_at', [$start, $end])->get(['created_at']),
        ];

        foreach ($activitySources as $records) {
            foreach ($records as $record) {
                $dayIndex = (int) $record->created_at->format('N') - 1;
                $hourIndex = (int) $record->created_at->format('G');
                if (isset($heatmap[$dayIndex]['hours'][$hourIndex])) {
                    $heatmap[$dayIndex]['hours'][$hourIndex] += 1;
                }
            }
        }

        return $heatmap;
    }

    private function buildPeakHours(): array
    {
        $start = Carbon::now()->subDays(30);
        $end = Carbon::now();

        $hours = array_fill(0, 24, ['activity' => 0, 'users' => 0]);

        $messageStats = Message::whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(function($message) {
                return $message->created_at->hour;
            })
            ->map(function($messages) {
                return [
                    'hour' => $messages->first()->created_at->hour,
                    'total' => $messages->count(),
                    'users' => $messages->unique('sender_id')->count()
                ];
            });

        foreach ($messageStats as $stat) {
            $hour = (int) $stat['hour'];
            $hours[$hour]['activity'] += (int) $stat['total'];
            $hours[$hour]['users'] += (int) $stat['users'];
        }

        $applicationStats = Application::whereBetween('created_at', [$start, $end])
            ->get()
            ->groupBy(function($application) {
                return $application->created_at->hour;
            })
            ->map(function($applications) {
                return [
                    'hour' => $applications->first()->created_at->hour,
                    'total' => $applications->count(),
                    'users' => $applications->unique('developer_id')->count()
                ];
            });

        foreach ($applicationStats as $stat) {
            $hour = (int) $stat['hour'];
            $hours[$hour]['activity'] += (int) $stat['total'];
            $hours[$hour]['users'] += (int) $stat['users'];
        }

        $maxActivity = max(1, ...array_map(fn ($value) => $value['activity'], $hours));

        return collect($hours)
            ->map(function ($data, $hour) use ($maxActivity) {
                $label = sprintf('%02d:00-%02d:00', $hour, ($hour + 1) % 24);
                return [
                    'hour' => $label,
                    'activity' => round(($data['activity'] / $maxActivity) * 100),
                    'users' => $data['users'],
                ];
            })
            ->sortByDesc('activity')
            ->take(5)
            ->values()
            ->all();
    }

    private function buildUserEngagement(Carbon $start, Carbon $end): array
    {
        $totalUsers = max(1, User::count());
        $newUsers = User::whereBetween('created_at', [$start, $end])->count();

        $messageUserIds = Message::whereBetween('created_at', [$start, $end])
            ->pluck('sender_id')
            ->unique();
        $applicationUserIds = Application::whereBetween('created_at', [$start, $end])
            ->pluck('developer_id')
            ->unique();
        $activeUsers = $messageUserIds->merge($applicationUserIds)->unique()->count();
        $returningUsers = max(0, $activeUsers - $newUsers);

        return [
            [
                'type' => 'Nuevos usuarios',
                'percentage' => round(($newUsers / $totalUsers) * 100),
                'color' => 'var(--color-chart-1)',
            ],
            [
                'type' => 'Usuarios activos',
                'percentage' => round(($activeUsers / $totalUsers) * 100),
                'color' => 'var(--color-chart-2)',
            ],
            [
                'type' => 'Usuarios recurrentes',
                'percentage' => round(($returningUsers / $totalUsers) * 100),
                'color' => 'var(--color-chart-3)',
            ],
        ];
    }

    private function buildActivityTrends(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $currentMessages = Message::whereBetween('created_at', [$start, $end])->count();
        $currentApplications = Application::whereBetween('created_at', [$start, $end])->count();
        $currentActiveUsers = max(1, Message::whereBetween('created_at', [$start, $end])->distinct('sender_id')->count('sender_id'));

        $prevMessages = Message::whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $prevApplications = Application::whereBetween('created_at', [$prevStart, $prevEnd])->count();
        $prevActiveUsers = max(1, Message::whereBetween('created_at', [$prevStart, $prevEnd])->distinct('sender_id')->count('sender_id'));

        $currentInteractions = $currentMessages + $currentApplications;
        $prevInteractions = $prevMessages + $prevApplications;

        $avgSessionTime = min(60, round(5 + ($currentInteractions / $currentActiveUsers) * 2));
        $avgSessionTimePrev = min(60, round(5 + ($prevInteractions / $prevActiveUsers) * 2));

        $pagesPerSession = round(2 + ($currentInteractions / $currentActiveUsers), 1);
        $pagesPerSessionPrev = round(2 + ($prevInteractions / $prevActiveUsers), 1);

        $bounceRate = max(0, min(100, round(70 - ($currentInteractions / $currentActiveUsers) * 8)));
        $bounceRatePrev = max(0, min(100, round(70 - ($prevInteractions / $prevActiveUsers) * 8)));

        $interactionsPerSession = round($currentInteractions / $currentActiveUsers, 1);
        $interactionsPerSessionPrev = round($prevInteractions / $prevActiveUsers, 1);

        return [
            [
                'metric' => 'Tiempo en plataforma',
                'current' => $avgSessionTime . ' min',
                'previous' => $avgSessionTimePrev . ' min',
                'trend' => $avgSessionTime >= $avgSessionTimePrev ? 'up' : 'down',
            ],
            [
                'metric' => 'Páginas por sesión',
                'current' => (string) $pagesPerSession,
                'previous' => (string) $pagesPerSessionPrev,
                'trend' => $pagesPerSession >= $pagesPerSessionPrev ? 'up' : 'down',
            ],
            [
                'metric' => 'Tasa de rebote',
                'current' => $bounceRate . '%',
                'previous' => $bounceRatePrev . '%',
                'trend' => $bounceRate <= $bounceRatePrev ? 'up' : 'down',
            ],
            [
                'metric' => 'Interacciones por sesión',
                'current' => (string) $interactionsPerSession,
                'previous' => (string) $interactionsPerSessionPrev,
                'trend' => $interactionsPerSession >= $interactionsPerSessionPrev ? 'up' : 'down',
            ],
        ];
    }

    private function buildGeographicData(): array
    {
        $companyCountries = \App\Models\CompanyProfile::whereNotNull('country')->pluck('country');
        $developerCountries = \App\Models\DeveloperProfile::whereNotNull('country')->pluck('country');
        $countries = $companyCountries->merge($developerCountries);

        $totals = $countries->countBy()->sortDesc();
        $totalUsers = max(1, $totals->sum());

        $top = $totals->take(5);
        $others = $totals->slice(5)->sum();

        $result = $top->map(function ($count, $country) use ($totalUsers) {
            return [
                'country' => $country,
                'users' => $count,
                'percentage' => round(($count / $totalUsers) * 100, 1),
                'flag' => '',
            ];
        })->values()->all();

        if ($others > 0) {
            $result[] = [
                'country' => 'Otros',
                'users' => $others,
                'percentage' => round(($others / $totalUsers) * 100, 1),
                'flag' => '🌍',
            ];
        }

        return $result;
    }


    private function buildRetentionData(string $period): array
    {
        $now = Carbon::now();
        $cohorts = [];
        $steps = 4;

        for ($i = 0; $i < 3; $i++) {
            [$start, $end] = $this->periodRange($period, $i);
            $label = match ($period) {
                'day' => $i === 0 ? 'Hoy' : ($i === 1 ? 'Ayer' : 'Anteayer'),
                'week' => $i === 0 ? 'Esta Semana' : ($i === 1 ? 'Semana Pasada' : 'Hace 2 Semanas'),
                'year' => (string) $now->copy()->subYears($i)->year,
                default => $now->copy()->subMonths($i)->translatedFormat('F Y'),
            };

            $cohortUsers = User::whereBetween('created_at', [$start, $end])->pluck('id');
            $total = max(1, $cohortUsers->count());

            $retention = [];
            for ($step = 1; $step <= $steps; $step++) {
                $activityStart = match ($period) {
                    'day' => $start->copy()->addHours($step),
                    'week' => $start->copy()->addDays($step),
                    'year' => $start->copy()->addMonths($step * 3),
                    default => $start->copy()->addWeeks($step),
                };
                $activityEnd = match ($period) {
                    'day' => $activityStart->copy()->addHour(),
                    'week' => $activityStart->copy()->addDay(),
                    'year' => $activityStart->copy()->addMonths(3),
                    default => $activityStart->copy()->addWeek(),
                };

                $activeUsers = Message::whereBetween('created_at', [$activityStart, $activityEnd])
                    ->whereIn('sender_id', $cohortUsers)
                    ->distinct('sender_id')
                    ->count('sender_id');

                $retention[] = $total > 0 ? round(($activeUsers / $total) * 100) : 0;
            }

            $cohorts[] = [
                'period' => $label,
                'retention' => $retention,
            ];
        }

        return $cohorts;
    }

    /**
     * Obtener todas las comisiones de la plataforma
     */
    public function commissions(): JsonResponse
    {
        abort_unless(auth()->user()->user_type === 'admin', 403);
        
        try {
            $commissions = \App\Models\PlatformCommission::with(['project', 'company', 'developer'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $commissions
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener comisiones.'
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de comisiones para el dashboard
     * OPTIMIZADO: Una sola consulta con groupBy
     */
    public function commissionStats(): JsonResponse
    {
        abort_unless(auth()->user()->user_type === 'admin', 403);
        
        try {
            // Usar una sola consulta con groupBy para obtener todas las estadísticas
            $stats = \App\Models\PlatformCommission::selectRaw("
               COUNT(*) as total_projects,
               SUM(CASE WHEN status = 'released' THEN commission_amount ELSE 0 END) as total_commission,
               SUM(CASE WHEN status = 'pending' THEN held_amount ELSE 0 END) as total_held,
               SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
               SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) as released_count,
               AVG(CASE WHEN status = 'released' THEN commission_amount ELSE NULL END) as avg_commission
            ")->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_commission' => round($stats->total_commission ?? 0, 2),
                    'total_held' => round($stats->total_held ?? 0, 2),
                    'pending_count' => (int) ($stats->pending_count ?? 0),
                    'released_count' => (int) ($stats->released_count ?? 0),
                    'total_projects' => (int) ($stats->total_projects ?? 0),
                    'average_commission' => round($stats->avg_commission ?? 0, 2),
                ]
            ]);
        } catch (\Exception $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas de comisiones.'
            ], 500);
        }
    }
}
