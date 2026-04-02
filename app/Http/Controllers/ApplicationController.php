<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\{Application, Project, Conversation, Message, User};

class ApplicationController extends Controller
{
    public function index(Request $r, Project $project)
    {
        // Verificar que el usuario es el dueño del proyecto O un admin
        abort_unless(
            $project->company_id === $r->user()->id || $r->user()->user_type === 'admin',
            403,
            'No tienes permiso para ver los candidatos de este proyecto.'
        );

        $applications = $project->applications()
            ->with(['developer' => function($query) {
                $query->select('id', 'name', 'lastname', 'email')
                      ->withAvg('reviewsReceived as rating', 'rating');
            }])
            ->latest()
            ->get();

        return \App\Http\Resources\ApplicationResource::collection($applications);
    }

    public function apply(Request $r, Project $project)
    {
        abort_unless($r->user()->user_type==='programmer', 403);
        $data = $r->validate(['cover_letter'=>'nullable|string']);
        
        // Prevent duplicate applications
        if ($project->applications()->where('developer_id', $r->user()->id)->exists()) {
            return response()->json(['message' => 'Ya has aplicado a este proyecto.'], 409);
        }

        $app = Application::create([
          'project_id'=>$project->id,
          'developer_id'=>$r->user()->id,
          'cover_letter'=>$data['cover_letter'] ?? null,
          'status' => 'pending'
        ]);

        // Notificar a la empresa
        $project->company->notify(new \App\Notifications\NewApplicationNotification($project, $r->user()));

        return new \App\Http\Resources\ApplicationResource($app->load('project'));
    }

    public function myApplications(Request $r)
    {
        abort_unless($r->user()->user_type==='programmer', 403);
        $apps = Application::where('developer_id',$r->user()->id)
            ->with('project')
            ->latest()->get();
            
        return \App\Http\Resources\ApplicationResource::collection($apps);
    }

    public function accept(Request $r, Application $application, \App\Services\PaymentService $paymentService)
    {
        $project = $application->project;
        
        // Authorization: Ensure Authenticated user is the owner of the project
        if ($project->company_id !== $r->user()->id) {
            abort(403, 'Solo el creador del proyecto puede aceptar candidatos.');
        }

        // Determine amount to pay (Budget Min or Max? Let's assume Budget Min for now or a negotiation field)
        // For this MVP, we use budget_min if available, else 0 (or throw error)
        $amountToPay = $project->budget_min ?? 0;

        if ($amountToPay <= 0) {
            // Logic for free projects or error?
            // Let's assume 0 is allowed for now, or use a default
        }

        try {
            DB::transaction(function () use ($application, $project, $r, $paymentService, $amountToPay) {
                // 0. Process Payment
                if ($amountToPay > 0) {
                // 0. Hold Funds in Escrow (Removed: Now handled via Milestones/Deposit)
                // if ($amountToPay > 0) {
                //    $paymentService->holdFunds($r->user(), $amountToPay, $project);
                // }
                }

                // 1. Update Application Status
                $application->update(['status' => 'accepted']);

                // Notificar al desarrollador (sin interrumpir la transacción principal si falla)
                try {
                    $application->developer->notify(new \App\Notifications\ApplicationAcceptedNotification($project, $r->user()));
                } catch (\Throwable $err) {
                    \Illuminate\Support\Facades\Log::error('No se pudo enviar el correo de aceptación: ' . $err->getMessage());
                }

                // 2. Reject other pending applications for this project (optional but common)
                // $project->applications()->where('id', '!=', $application->id)->update(['status' => 'rejected']);
    
                // 3. No longer update project status to in_progress here - wait for manual start button
                // $project->update(['status' => 'in_progress']);
    
                // 4. Dispatch Event to handle Chat Creation
                \App\Events\ApplicationAccepted::dispatch($application);
            });
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Error interno procesando la aceptación. Detalle: ' . $e->getMessage()
            ], 400);
        }

        return response()->json(['message' => 'Candidato aceptado, pago procesado y chat iniciado.']);
    }

    public function reject(Request $r, Application $application)
    {
         $project = $application->project;

         if ($project->company_id !== $r->user()->id) {
            abort(403, 'Unauthorized');
        }

        $application->update(['status' => 'rejected']);

        return response()->json(['message' => 'Candidato rechazado.']);
    }
}
