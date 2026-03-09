<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Favorite;
use App\Models\DeveloperProgress;

class ProjectController extends Controller
{

    public function fund(Request $r, Project $project)
    {
        if ($project->company_id !== $r->user()->id) {
            abort(403);
        }

        // Validate amount (50% of budget_min or full amount?)
        // User request: "Anticipo del 50%"
        // Usar budget_max si está disponible, sino budget_min
        $totalBudget = $project->budget_max ?? $project->budget_min ?? 0;
        $amount = $totalBudget * 0.5;

        try {
            $paymentService = app(\App\Services\PaymentService::class);
            $paymentService->fundProject($r->user(), $amount, $project);
            
            // Crear registro de comisión SOLO si no existe uno previamente
            $existingCommission = \App\Models\PlatformCommission::where('project_id', $project->id)->first();
            if (!$existingCommission) {
                $paymentService->createCommissionRecord($r->user(), $project, $totalBudget);
            }

            return response()->json(['message' => 'Proyecto financiado con éxito.', 'project' => $project]);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 400);
        }
    }

    public function index(Request $r)
    {
        // Log de autenticación para debug
        \Log::info('ProjectController@index - User:', [
            'id' => $r->user()?->id,
            'email' => $r->user()?->email,
            'user_type' => $r->user()?->user_type,
            'token_exists' => !empty($r->bearerToken()),
        ]);
        
        // filtros simples: status, search
        $q = Project::query()
            ->with([
                'company:id,name,email_verified_at',
                'categories:id,name',
                'skills:id,name',
            ])
            ->withCount(['applications', 'milestones', 'milestones as completed_milestones_count' => function ($query) {
                $query->where('progress_status', 'completed');
            }])
            ->withExists(['applications as has_applied' => function ($query) use ($r) {
                $query->where('developer_id', $r->user()->id ?? 0);
            }]);
            
        // Por defecto, solo mostrar proyectos abiertos para programadores
        if (! $r->filled('status') && ($r->user()->user_type === 'programmer')) {
            $q->where('status', 'open');
        }
        
        if ($r->filled('status')) {
            $q->where('status', $r->status);
        }
        if ($r->filled('search')) {
            $q->where(function ($builder) use ($r) {
                $builder
                    ->where('title', 'like', '%' . $r->search . '%')
                    ->orWhere('description', 'like', '%' . $r->search . '%');
            });
        }
        if ($r->filled('category')) {
            $q->whereHas('categories', function ($builder) use ($r) {
                $builder->where('name', $r->category)
                    ->orWhere('id', $r->category);
            });
        }
        if ($r->filled('skill')) {
            $q->whereHas('skills', function ($builder) use ($r) {
                $builder->where('name', $r->skill)
                    ->orWhere('id', $r->skill);
            });
        }
        if ($r->filled('level')) {
            $q->where('level', $r->level);
        }
        if ($r->filled('remote')) {
            $q->where('remote', filter_var($r->remote, FILTER_VALIDATE_BOOLEAN));
        }
        if ($r->filled('my_projects')) {
             $q->whereHas('applications', function ($query) use ($r) {
                 $query->where('developer_id', $r->user()->id)->where('status', 'accepted');
             });
        }
        return \App\Http\Resources\ProjectResource::collection($q->latest()->paginate());
    }

    public function show(Project $project)
    {
        $project->load(['company', 'categories', 'skills', 'applications.developer']);
        // If owner, load applications with developers
        // Logic for loading applications could be conditional or separate, but ProjectResource handles 'whenLoaded'
        
        return new \App\Http\Resources\ProjectResource($project);
    }

    public function start(Request $request, Project $project)
    {
        abort_unless($request->user()->user_type === 'company' && $project->company_id === $request->user()->id, 403);

        // Check if project has at least one accepted developer
        $acceptedApplications = $project->applications()->where('status', 'accepted')->count();
        if ($acceptedApplications === 0) {
            return response()->json(['message' => 'El proyecto debe tener al menos un desarrollador aceptado para iniciar'], 400);
        }

        // Update project status to in_progress
        $project->update(['status' => 'in_progress']);

        // Create developer progress records for all accepted developers
        $acceptedDevelopers = $project->applications()
            ->where('status', 'accepted')
            ->pluck('developer_id');
        
        foreach ($acceptedDevelopers as $developerId) {
            DeveloperProgress::firstOrCreate([
                'project_id' => $project->id,
                'developer_id' => $developerId
            ], [
                'progress' => 0,
                'milestones_completed' => json_encode([]),
                'tasks_completed' => json_encode([])
            ]);
        }

        // Create a group chat for the project
        $conversation = Conversation::create([
            'name' => $project->title,
            'is_group' => true
        ]);

        // Add company user to the conversation
        $conversation->participants()->attach($request->user()->id);

        // Add all accepted developers to the conversation
        $conversation->participants()->attach($acceptedDevelopers);

        return response()->json(['message' => 'Proyecto iniciado con éxito', 'project' => new \App\Http\Resources\ProjectResource($project)]);
    }

    /**
     * Obtener el progreso general de los desarrolladores en un proyecto
     */
    public function getDeveloperProgress(Request $request, Project $project)
    {
        abort_unless($request->user()->user_type === 'company' && $project->company_id === $request->user()->id, 403);

        $progress = $project->applications()
            ->where('status', 'accepted')
            ->with('developer:id,name,lastname,email,profile_picture')
            ->get()
            ->map(function ($app) use ($project) {
                // Obtener milestones asignados al desarrollador
                $assignedMilestones = $project->milestones()
                    ->where('assigned_developer_id', $app->developer_id)
                    ->get();
                
                $total = $assignedMilestones->count();
                
                if ($total === 0) {
                    return [
                        'developer_id' => $app->developer_id,
                        'developer' => $app->developer,
                        'progress' => 0,
                        'milestones_completed' => 0,
                        'total_milestones' => 0
                    ];
                }
                
                $completed = \App\Models\DeveloperMilestone::whereIn('milestone_id', $assignedMilestones->pluck('id'))
                    ->where('developer_id', $app->developer_id)
                    ->where('progress_status', 'completed')
                    ->count();
                
                $progressPercentage = round(($completed / $total) * 100);
                
                return [
                    'developer_id' => $app->developer_id,
                    'developer' => $app->developer,
                    'progress' => $progressPercentage,
                    'milestones_completed' => $completed,
                    'total_milestones' => $total
                ];
            });

        return response()->json(['data' => $progress]);
    }

    /**
     * Actualizar el progreso de un desarrollador en un proyecto
     */
    public function updateDeveloperProgress(Request $request, Project $project, $developerId)
    {
        abort_unless($request->user()->user_type === 'company' && $project->company_id === $request->user()->id, 403);

        $validatedData = $request->validate([
            'progress' => 'required|integer|min:0|max:100',
            'milestones_completed' => 'nullable|array',
            'tasks_completed' => 'nullable|array'
        ]);

        $progress = DeveloperProgress::firstOrCreate([
            'project_id' => $project->id,
            'developer_id' => $developerId
        ], $validatedData);

        if ($progress->exists) {
            $progress->update($validatedData);
        }

        return response()->json(['data' => $progress]);
    }

    public function companyProjects(Request $request)
    {
        abort_unless($request->user()->user_type === 'company', 403);

        $perPage = $request->get('per_page', 20);

        $projects = Project::with(['company', 'categories', 'skills'])
            ->withCount('applications')
            ->where('company_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return \App\Http\Resources\ProjectResource::collection($projects);
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->user_type==='company', 403);
        $data = $r->validate([
          'title'=>'required|string|max:150',
          'description'=>'required|string',
          'budget_min'=>'nullable|integer',
          'budget_max'=>'nullable|integer',
          'budget_type'=>'nullable|in:fixed,hourly',
          'duration_value'=>'nullable|integer|min:1',
          'duration_unit'=>'nullable|in:days,weeks,months',
          'location'=>'nullable|string|max:150',
          'remote'=>'nullable|boolean',
          'level'=>'nullable|in:junior,mid,senior,lead',
          'priority'=>'nullable|in:low,medium,high,urgent',
          'featured'=>'nullable|boolean',
          'deadline'=>'nullable|date',
          'max_applicants'=>'nullable|integer|min:1',
          'tags'=>'nullable|array',
          'status'=>'nullable|in:open,in_progress,completed,cancelled,draft,pending_payment',
          'category_ids'=>'nullable|array',
          'category_ids.*'=>'integer|exists:project_categories,id',
          'skill_ids'=>'nullable|array',
          'skill_ids.*'=>'integer|exists:skills,id',
        ]);
        $data['company_id'] = $r->user()->id;
        if (empty($data['status'])) {
            $data['status'] = 'open';
        }
        $project = Project::create($data);
        if (!empty($data['category_ids'])) {
            $project->categories()->sync($data['category_ids']);
        }
        if (!empty($data['skill_ids'])) {
            $project->skills()->sync($data['skill_ids']);
        }
        return new \App\Http\Resources\ProjectResource($project);
    }

    public function update(Request $r, Project $project)
    {
        abort_unless($r->user()->user_type==='company' && $project->company_id==$r->user()->id, 403);
        $data = $r->validate([
            'title'=>'sometimes|string|max:150',
            'description'=>'sometimes|string',
            'budget_min'=>'nullable|integer',
            'budget_max'=>'nullable|integer',
            'budget_type'=>'nullable|in:fixed,hourly',
            'duration_value'=>'nullable|integer|min:1',
            'duration_unit'=>'nullable|in:days,weeks,months',
            'location'=>'nullable|string|max:150',
            'remote'=>'nullable|boolean',
            'level'=>'nullable|in:junior,mid,senior,lead',
            'priority'=>'nullable|in:low,medium,high,urgent',
            'featured'=>'nullable|boolean',
            'deadline'=>'nullable|date',
            'max_applicants'=>'nullable|integer|min:1',
            'tags'=>'nullable|array',
            'status'=>'nullable|in:open,in_progress,completed,cancelled,draft,pending_payment',
            'category_ids'=>'nullable|array',
            'category_ids.*'=>'integer|exists:project_categories,id',
            'skill_ids'=>'nullable|array',
            'skill_ids.*'=>'integer|exists:skills,id',
        ]);
        // Check if completing project
        if (($data['status'] ?? '') === 'completed' && $project->status !== 'completed') {
             // Find accepted application
             $acceptedApp = $project->applications()->where('status', 'accepted')->first();
             
             if ($acceptedApp) {
                  // Calcular el presupuesto total (usar budget_max si está disponible)
                  $totalBudget = $project->budget_max ?? $project->budget_min ?? 0;
                  $remainingToFund = $totalBudget * 0.5;
                  
                  // Calcular el monto restante por pagar (50% del total)
                  $remainingToPay = $totalBudget * 0.5;
                  
                  // 2. Wrap in transaction: Fund remaining 50% -> Release remaining 50%
                  try {
                      \Illuminate\Support\Facades\DB::transaction(function () use ($r, $project, $acceptedApp, $totalBudget, $remainingToFund, $remainingToPay) {
                          $paymentService = app(\App\Services\PaymentService::class);
                          
                          // Fund the remaining 50%
                          if ($remainingToFund > 0) {
                              $paymentService->fundProject($r->user(), $remainingToFund, $project);
                          }

                          // Release only the remaining 50% (not 100%) to avoid double payment
                          if ($remainingToPay > 0) {
                              $paymentService->releaseFunds($r->user(), $remainingToPay, $project);
                          }
                      });
                  } catch (\Exception $e) {
                      return response()->json(['message' => 'Error en el proceso de pago final: ' . $e->getMessage()], 400);
                  }
             }
        }
        
        $project->update($data);
        if (array_key_exists('category_ids', $data)) {
            $project->categories()->sync($data['category_ids'] ?? []);
        }
        if (array_key_exists('skill_ids', $data)) {
            $project->skills()->sync($data['skill_ids'] ?? []);
        }
        return new \App\Http\Resources\ProjectResource($project);
    }

    public function destroy(Request $r, Project $project)
    {
        abort_unless($r->user()->user_type==='company' && $project->company_id==$r->user()->id, 403);
        // Verificar si tiene desarrolladores asignados (aplicaciones aceptadas)
        $hasAcceptedDevelopers = $project->applications()->where('status', 'accepted')->exists();
        
        if ($hasAcceptedDevelopers) {
             return response()->json([
                'message' => 'No se puede eliminar un proyecto que ya tiene un desarrollador asignado.'
             ], 403);
        }

        $project->delete();
        return response()->noContent();
    }

    /**
     * Finalizar proyecto - Cobra el 50% restante y paga a los freelancers
     * Solo se puede llamar cuando todas las milestones están completadas
     */
    public function complete(Request $r, Project $project)
    {
        // Verificar que el usuario es la empresa dueña del proyecto
        abort_unless($r->user()->user_type === 'company' && $project->company_id === $r->user()->id, 403);

        // Verificar que el proyecto está en progreso
        if ($project->status !== 'in_progress') {
            return response()->json([
                'message' => 'El proyecto debe estar en progreso para poder finalizarlo.'
            ], 400);
        }

        // Verificar que hay al menos un desarrollador asignado
        $acceptedApps = $project->applications()->where('status', 'accepted')->get();
        if ($acceptedApps->isEmpty()) {
            return response()->json([
                'message' => 'No hay desarrolladores asignados a este proyecto.'
            ], 400);
        }

        // Verificar que todas las milestones están completadas por todos los desarrolladores
        $totalMilestones = $project->milestones()->count();
        $acceptedDevsCount = $acceptedApps->count();
        $totalExpectedCompletions = $totalMilestones * $acceptedDevsCount;

        if ($totalMilestones === 0) {
            return response()->json([
                'message' => 'El proyecto debe tener al menos una milestone para poder finalizarlo.'
            ], 400);
        }

        $completedMilestones = \App\Models\DeveloperMilestone::whereIn('milestone_id', $project->milestones->pluck('id'))
            ->where('progress_status', 'completed')
            ->count();

        if ($completedMilestones < $totalExpectedCompletions) {
            return response()->json([
                'message' => 'No se puede finalizar el proyecto. Hay milestones pendientes de completar por algunos desarrolladores.',
                'completed' => $completedMilestones,
                'total' => $totalExpectedCompletions,
                'progress_percentage' => round(($completedMilestones / $totalExpectedCompletions) * 100)
            ], 400);
        }

        // Calcular el presupuesto total (usar budget_max si está disponible, sino budget_min)
        $totalBudget = $project->budget_max ?? $project->budget_min ?? 0;
        $remainingToFund = $totalBudget * 0.5; // El 50% restante

        // Calcular el monto restante por pagar (50% del total a dividir entre devs)
        $remainingToPay = $totalBudget * 0.5;
        $remainingToPayPerDev = round($remainingToPay / $acceptedDevsCount, 2);

        // Verificar que la empresa tenga suficientes fondos para el 50% restante
        $wallet = $r->user()->wallet()->firstOrCreate(['user_id' => $r->user()->id], ['balance' => 0]);
        $availableBalance = $wallet->balance;
        
        if ($availableBalance < $remainingToFund) {
            return response()->json([
                'message' => 'Saldo insuficiente para completar el proyecto.',
                'required' => $remainingToFund,
                'available' => $availableBalance,
            ], 400);
        }

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($r, $project, $acceptedApps, $totalBudget, $remainingToFund, $remainingToPayPerDev) {
                $paymentService = app(\App\Services\PaymentService::class);

                // 1. Cobrar el 50% restante del proyecto (si no se ha financiado completamente)
                // NOTA: El primer 50% ya fue financiado al inicio del proyecto
                if ($remainingToFund > 0) {
                    $paymentService->fundProject($r->user(), $remainingToFund, $project);
                }

                // 2. Liberar el 50% RESTANTE a los freelancers dividiendo entre ellos
                if ($remainingToPayPerDev > 0) {
                    // Update: releaseFunds service needs the individual app to know who to pay
                    foreach ($acceptedApps as $app) {
                       $paymentService->releaseFundsToDeveloper($app, $remainingToPayPerDev, $project);
                    }
                }

                // 3. Marcar el proyecto como completado
                $project->update(['status' => 'completed']);
            });

            return response()->json([
                'message' => 'Proyecto completado exitosamente. El pago ha sido liberado al freelancer.',
                'project' => new \App\Http\Resources\ProjectResource($project->fresh())
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error en el proceso de pago: ' . $e->getMessage()
            ], 400);
        }
    }
}
