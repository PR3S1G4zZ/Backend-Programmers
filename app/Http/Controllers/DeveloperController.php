<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeveloperController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::where('user_type', 'programmer')
            ->with('developerProfile')
            ->withCount('reviewsReceived')
            ->withAvg('reviewsReceived', 'rating')
            ->withCount([
                'applications as completed_projects_count' => function ($q) {
                    $q->whereHas('project', fn($b) => $b->where('status', 'completed'));
                }
            ]);

        if ($request->filled('search')) {
            $query->where(function ($builder) use ($request) {
                $builder
                    ->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('lastname', 'like', '%' . $request->search . '%');
            });
        }

        $developers = $query->paginate(15)->through(function ($developer) {
            $profile = $developer->developerProfile;
            
            $completedProjects = $developer->completed_projects_count ?? 0;

            return [
                'id' => (string) $developer->id,
                'name' => $developer->name . ' ' . $developer->lastname,
                'title' => $profile?->headline ?? 'Desarrollador',
                'location' => $profile?->location ?? 'Sin ubicación',
                'hourlyRate' => $profile?->hourly_rate ?? null,
                'rating' => round($developer->reviews_received_avg_rating ?? 0, 1),
                'reviewsCount' => $developer->reviews_received_count ?? 0,
                'completedProjects' => $completedProjects,
                'availability' => $profile?->availability ?? 'available',
                'skills' => $profile?->skills ?? [],
                'experience' => $profile?->experience_years ?? null,
                'languages' => $profile?->languages ?? [],
                'bio' => $profile?->bio ?? '',
                'lastActive' => $developer->updated_at?->diffForHumans(),
                'isVerified' => $developer->email_verified_at !== null,
                'profilePicture' => $developer->profile_picture ?? null,
            ];
        });

        return response()->json([
            'success' => true,
            ...$developers->toArray(),
        ]);
    }
    public function show($id): JsonResponse
    {
        $developer = User::where('user_type', 'programmer')
            ->where('id', $id)
            ->with('developerProfile')
            ->withCount('reviewsReceived')
            ->withAvg('reviewsReceived', 'rating')
            ->withCount([
                'applications as completed_projects_count' => function ($q) {
                    $q->whereHas('project', fn($b) => $b->where('status', 'completed'));
                }
            ])
            ->firstOrFail();

        $profile = $developer->developerProfile;
        $completedProjects = $developer->completed_projects_count ?? 0;

        // Get completed projects details
        $completedProjectsList = $developer->applications()
            ->whereHas('project', function ($builder) {
                $builder->where('status', 'completed');
            })
            ->with(['project', 'project.company'])
            ->get()
            ->map(function ($application) {
                return [
                    'id' => $application->project->id,
                    'title' => $application->project->title,
                    'description' => $application->project->description,
                    'budget_min' => $application->project->budget_min,
                    'budget_max' => $application->project->budget_max,
                    'company_name' => $application->project->company->name ?? 'Empresa',
                    'completed_at' => $application->project->updated_at->format('Y-m-d'),
                ];
            });

        $data = [
            'id' => (string) $developer->id,
            'name' => $developer->name . ' ' . $developer->lastname,
            'email' => $developer->email, // Added email for contact info if needed
            'title' => $profile?->headline ?? 'Desarrollador',
            'location' => $profile?->location ?? 'Sin ubicación',
            'hourlyRate' => $profile?->hourly_rate ?? null,
            'rating' => round($developer->reviews_received_avg_rating ?? 0, 1),
            'reviewsCount' => $developer->reviews_received_count ?? 0,
            'completedProjects' => $completedProjects,
            'completedProjectsList' => $completedProjectsList,
            'availability' => $profile?->availability ?? 'available',
            'skills' => $profile?->skills ?? [],
            'experience' => $profile?->experience_years ?? null, // Note: The frontend expects array of objects for experience? logic in ProfileSection suggests so. Let's check ProfileSection again or the Model.
            // ProfileSection uses "experience" state which is an array of objects {company, position...}. 
            // the DeveloperController index uses $profile?->experience_years which seems to be a number? 
            // valid point. The `create_developer_profiles_table` migration should be checked.
            // But for now let's return what we have in the DB.
            'experience_details' => $profile?->experience ?? [], // Assuming 'experience' column stores JSON or similar
            'languages' => $profile?->languages ?? [],
            'bio' => $profile?->bio ?? '',
            'links' => $profile?->links ?? [],
            'lastActive' => $developer->updated_at?->diffForHumans(),
            'isVerified' => $developer->email_verified_at !== null,
            'joinedAt' => $developer->created_at->format('M Y'),
            'profilePicture' => $developer->profile_picture ?? null,
        ];

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Obtener los proyectos completados del desarrollador autenticado
     */
    public function myCompletedProjects(Request $request): JsonResponse
    {
        $developer = $request->user();
        
        if ($developer->user_type !== 'programmer') {
            return response()->json([
                'success' => false,
                'message' => 'Solo los desarrolladores pueden ver sus proyectos completados'
            ], 403);
        }

        $completedProjects = $developer->applications()
            ->whereHas('project', function ($builder) {
                $builder->where('status', 'completed');
            })
            ->with(['project', 'project.company'])
            ->get()
            ->map(function ($application) {
                return [
                    'id' => $application->project->id,
                    'title' => $application->project->title,
                    'description' => $application->project->description,
                    'budget_min' => $application->project->budget_min,
                    'budget_max' => $application->project->budget_max,
                    'company_name' => $application->project->company->name ?? 'Empresa',
                    'company_id' => $application->project->company_id,
                    'completed_at' => $application->project->updated_at->format('Y-m-d'),
                    'created_at' => $application->project->created_at->format('Y-m-d'),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $completedProjects,
        ]);
    }
}
