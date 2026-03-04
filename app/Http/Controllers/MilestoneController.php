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

        $milestones = $project->milestones()->orderBy('order')->get();

        if ($user->user_type === 'programmer') {
            // Load developer specific progress
            foreach ($milestones as $milestone) {
                $devMilestone = \App\Models\DeveloperMilestone::firstOrCreate(
                    ['milestone_id' => $milestone->id, 'developer_id' => $user->id],
                    ['progress_status' => 'todo', 'deliverables' => []]
                );
                $milestone->progress_status = $devMilestone->progress_status;
                $milestone->deliverables = $devMilestone->deliverables;
                $milestone->developer_milestone_id = $devMilestone->id;
            }
        } else if ($user->user_type === 'company' && $request->has('developer_id')) {
            // Company viewing a specific developer's progress
            $developerId = $request->input('developer_id');
            foreach ($milestones as $milestone) {
                $devMilestone = \App\Models\DeveloperMilestone::firstOrCreate(
                    ['milestone_id' => $milestone->id, 'developer_id' => $developerId],
                    ['progress_status' => 'todo', 'deliverables' => []]
                );
                $milestone->progress_status = $devMilestone->progress_status;
                $milestone->deliverables = $devMilestone->deliverables;
                $milestone->developer_milestone_id = $devMilestone->id;
                $milestone->developer_id = $developerId;
            }
        }

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
            'order' => 'integer'
        ]);

        $milestone = $project->milestones()->create($data);

        return response()->json($milestone, 201);
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
            'developer_id' => 'sometimes|exists:users,id'
        ]);
        
        $user = $request->user();
        $isDeveloper = $user->id !== $project->company_id && 
            $project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists();
        
        if ($isDeveloper && isset($data['amount'])) {
            unset($data['amount']);
        }

        // Si se envía progress_status, actualizamos el estado INDIVIDUAL en DeveloperMilestone
        if (isset($data['progress_status'])) {
            $developerId = $isDeveloper ? $user->id : $request->input('developer_id');
            
            if (!$developerId) {
                return response()->json(['message' => 'developer_id es requerido para actualizar el estado.'], 400);
            }

            $devMilestone = \App\Models\DeveloperMilestone::firstOrCreate(
                ['milestone_id' => $milestone->id, 'developer_id' => $developerId],
                ['progress_status' => 'todo', 'deliverables' => []]
            );

            $newStatus = $data['progress_status'];
            $currentStatus = $devMilestone->progress_status;

            if ($newStatus === 'review' && $currentStatus !== 'review') {
                return response()->json(['message' => 'Para enviar a revisión, debes usar la opción de "Entregar" y adjuntar entregables.'], 400);
            }

            if ($newStatus === 'completed' && $currentStatus !== 'completed') {
                return response()->json(['message' => 'Para completar, la empresa debe aprobar el hito explícitamente.'], 400);
            }

            $devMilestone->update(['progress_status' => $newStatus]);

            // Eliminamos variables de progreso del array principal
            unset($data['progress_status']);
            unset($data['developer_id']);
        }

        // Actualizar datos base del hito si quedan
        if (!empty($data)) {
            $milestone->update($data);
        }

        return response()->json($milestone);
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

        $data = $request->validate([
            'deliverables' => 'required|array',
            'deliverables.*' => 'required|string'
        ]);

        $devMilestone = \App\Models\DeveloperMilestone::firstOrCreate(
            ['milestone_id' => $milestone->id, 'developer_id' => $request->user()->id],
            ['progress_status' => 'todo']
        );

        $devMilestone->update([
            'deliverables' => $data['deliverables'],
            'progress_status' => 'review'
        ]);

        return response()->json($milestone);
    }

    public function approve(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('approve', $milestone);

        $developerId = $request->input('developer_id');
        if (!$developerId) {
            return response()->json(['message' => 'developer_id es requerido para aprobar.'], 400);
        }

        $devMilestone = \App\Models\DeveloperMilestone::where('milestone_id', $milestone->id)
            ->where('developer_id', $developerId)
            ->first();

        if (!$devMilestone || $devMilestone->progress_status !== 'review') {
            return response()->json(['message' => 'El hito no está en revisión para este desarrollador.'], 400);
        }

        // Eliminado pago de hito. Solo se marca como completado individualmente.
        $devMilestone->update([
            'progress_status' => 'completed',
        ]);

        return response()->json($milestone);
    }

    public function reject(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('reject', $milestone);

        $developerId = $request->input('developer_id');
        if (!$developerId) {
            return response()->json(['message' => 'developer_id es requerido para rechazar.'], 400);
        }

        $devMilestone = \App\Models\DeveloperMilestone::where('milestone_id', $milestone->id)
            ->where('developer_id', $developerId)
            ->first();

        if (!$devMilestone || $devMilestone->progress_status !== 'review') {
             return response()->json(['message' => 'El hito no está en revisión para este desarrollador.'], 400);
        }

        $devMilestone->update([
            'progress_status' => 'in_progress',
        ]);

        return response()->json($milestone);
    }
}
