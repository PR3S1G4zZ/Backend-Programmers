<?php

namespace App\Http\Controllers;

use App\Models\Milestone;
use App\Models\Project;
use Illuminate\Http\Request;

class MilestoneController extends Controller
{
    public function index(Request $request, Project $project)
    {
        // Policy check
        // We pass the class name and the project for the 'viewAny' check if needed, 
        // essentially validating if the user has access to THIS project's milestones.
        // Since our Policy viewAny returns true, we rely on the project relationship check here or in a middleware.
        // For stricter control:
        $user = $request->user();
        if ($user->id !== $project->company_id && 
            !$project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists()) {
             abort(403, 'Unauthorized');
        }

        return $project->milestones()->orderBy('order')->get();
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
            'due_date' => 'nullable|date'
        ]);
        
        // Restrict amount field for developers (only company can change amount)
        $user = $request->user();
        $isDeveloper = $user->id !== $project->company_id && 
            $project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists();
        
        if ($isDeveloper && isset($data['amount'])) {
            unset($data['amount']);
        }
        
        // Strict State Transition Logic
        if (isset($data['progress_status'])) {
            $newStatus = $data['progress_status'];
            $currentStatus = $milestone->progress_status;

            // Prevent direct jump to 'review' without 'submit' endpoint
            if ($newStatus === 'review' && $currentStatus !== 'review') {
                return response()->json(['message' => 'Para enviar a revisión, debes usar la opción de "Entregar" y adjuntar entregables.'], 400);
            }

            // Prevent direct jump to 'completed' without 'approve' endpoint
            if ($newStatus === 'completed' && $currentStatus !== 'completed') {
                return response()->json(['message' => 'Para completar, la empresa debe aprobar el hito explícitamente.'], 400);
            }
            
            // Allow moving back to 'in_progress' from 'review' (Reject logic handled in separate endpoint, but manual move allowed?)
            // Actually, Reject endpoint handles logic. Manual move by developer from review -> in_progress? 
            // If developer realizes they made a mistake.
            if ($currentStatus === 'review' && $newStatus === 'in_progress') {
                // Allow
            }
        }

        $milestone->update($data);

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

        $milestone->update([
            'deliverables' => $data['deliverables'],
            'progress_status' => 'review'
        ]);

        return response()->json($milestone);
    }

    public function approve(Request $request, Project $project, Milestone $milestone)
    {
        $this->authorize('approve', $milestone);

        if ($milestone->progress_status !== 'review') {
            return response()->json(['message' => 'El hito no está en revisión.'], 400);
        }

        try {
            $paymentService = app(\App\Services\PaymentService::class);
            $paymentService->releaseMilestone($request->user(), $milestone->amount, $project);
            
            $milestone->update([
                'progress_status' => 'completed',
                // 'status' => 'released' // Keep status as 'funded' or whatever it was, maybe? Or just don't set to released.
                // Assuming 'status' field tracks payment status. If we don't release, maybe we shouldn't change it to 'released'.
                // Let's keep it simply as completed for progress.
            ]);

            return response()->json($milestone);

        } catch (\Exception $e) {
             return response()->json(['message' => 'Error liberando fondos: ' . $e->getMessage()], 400);
        }
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
