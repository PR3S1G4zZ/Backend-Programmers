<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(Request $request, Project $project)
    {
        $user = $request->user();
        if ($user->id !== $project->company_id && 
            !$project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists()) {
             abort(403, 'Unauthorized');
        }

        $query = $project->milestones()->with('developer:id,name')->orderBy('order');

        if ($user->user_type === 'company' && $request->has('developer_id')) {
            $developerId = $request->input('developer_id');
            // Verify the developer is an accepted applicant on the project
            $isValidDeveloper = $project->applications()
                ->where('developer_id', $developerId)
                ->where('status', 'accepted')
                ->exists();
                
            if ($isValidDeveloper) {
                $query->where('assigned_developer_id', $developerId);
            } else {
                // Return empty result if developer is not valid
                return response()->json([]);
            }
        } elseif ($user->user_type === 'programmer') {
            // For programmers, only show milestones assigned to them
            $query->where('assigned_developer_id', $user->id);
        }

        $milestones = $query->get();

        return response()->json($milestones);
    }

    public function store(Request $request, Project $project)
    {
        $this->authorize('create', [Milestone::class, $project]);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'due_date' => 'nullable|date',
            'order' => 'integer',
            'assigned_developer_id' => 'nullable|exists:users,id'
        ]);

        $milestone = $project->milestones()->create($data);

        return response()->json($milestone->load('developer:id,name'), 201);
    }

    public function update(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('update', $milestone);
        
        $data = $request->validate([
            'title' => 'sometimes|string',
            'description' => 'sometimes|string',
            'amount' => 'sometimes|numeric',
            'progress_status' => 'sometimes|in:todo,in_progress,review,completed',
            'due_date' => 'nullable|date',
            'assigned_developer_id' => 'sometimes|nullable|exists:users,id'
        ]);
        
        $user = $request->user();
        $isDeveloper = $user->id !== $project->company_id && 
            $project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists();
            
        if ($isDeveloper) {
            // Un desarrollador solo puede actualizar el estado de sus propios hitos
            if ($milestone->assigned_developer_id !== $user->id) {
                abort(403, 'Solo puedes actualizar hitos asignados a ti.');
            }
            // No puede cambiar el monto ni a quién está asignado
            unset($data['amount']);
            unset($data['assigned_developer_id']);
            
            // Validaciones de estado para desarrolladores
            if (isset($data['progress_status'])) {
                if ($data['progress_status'] === 'review' && $milestone->progress_status !== 'review') {
                    return response()->json(['message' => 'Para enviar a revisión, debes usar la opción de "Entregar" y adjuntar entregables.'], 400);
                }
                if ($data['progress_status'] === 'completed' && $milestone->progress_status !== 'completed') {
                    return response()->json(['message' => 'Para completar, la empresa debe aprobar el hito explícitamente.'], 400);
                }
            }
        }

        $milestone->update($data);

        return response()->json($milestone->load('developer:id,name'));
    }

    public function destroy(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('delete', $milestone);
        $milestone->delete();
        return response()->noContent();
    }

    public function submit(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('submit', $milestone);

        if ($milestone->assigned_developer_id !== $request->user()->id) {
            abort(403, 'Solo puedes entregar hitos asignados a ti.');
        }

        $data = $request->validate([
            'deliverables' => 'required|array',
            'deliverables.*' => 'required|string'
        ]);

        $milestone->update([
            'deliverables' => $data['deliverables'],
            'progress_status' => 'review'
        ]);

        // Notificar a la empresa
        $project->company->notify(new \App\Notifications\MilestoneSubmittedNotification($milestone, $project, $request->user()));

        return response()->json($milestone);
    }

    public function approve(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('approve', $milestone);

        if ($milestone->progress_status !== 'review') {
            return response()->json(['message' => 'El hito no está en revisión.'], 400);
        }

        $milestone->update([
            'progress_status' => 'completed',
        ]);

        // Notification — wrapped in try-catch so approval still succeeds if mail fails
        try {
            if ($milestone->assignedDeveloper) {
                $milestone->assignedDeveloper->notify(new \App\Notifications\MilestoneApprovedNotification($milestone, $project));
            }
        } catch (\Exception $e) {
            \Log::warning("Failed to send milestone approval notification: " . $e->getMessage());
        }

        // Release funds for the milestone — ONLY if amount > 0
        if ($milestone->amount > 0) {
            try {
                $paymentService = app(\App\Services\PaymentService::class);
                $paymentService->releaseMilestone($request->user(), $milestone->amount, $project);
            } catch (\Exception $e) {
                \Log::error("Failed to release funds for milestone #{$milestone->id}: " . $e->getMessage());
                // Milestone is already approved, but payment failed — log and inform
                return response()->json([
                    'milestone' => $milestone,
                    'payment_warning' => 'El hito fue aprobado pero hubo un problema al liberar los fondos: ' . $e->getMessage()
                ], 200);
            }
        }

        return response()->json($milestone);
    }

    public function reject(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('reject', $milestone);

        if ($milestone->progress_status !== 'review') {
             return response()->json(['message' => 'El hito no está en revisión.'], 400);
        }

        $milestone->update([
            'progress_status' => 'in_progress',
        ]);

        return response()->json($milestone);
    }
}
