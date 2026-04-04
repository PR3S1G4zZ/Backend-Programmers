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

        $developers = $query->get()->map(function ($developer) {
            $profile = $developer->developerProfile;
            
            $completedProjects = $developer->completed_projects_count ?? 0;

            $profilePicture = $developer->profile_picture ? asset('storage/' . $developer->profile_picture) : null;

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
                'profilePicture' => $profilePicture,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $developers,
        ]);
    }
    public function show($id): JsonResponse
    {
        $developer = User::where('user_type', 'programmer')
            ->where('id', $id)
            ->with(['developerProfile', 'portfolioProjects'])
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

        // Get portfolio projects (personal/external projects)
        $portfolioProjectsList = $developer->portfolioProjects->map(function ($project) {
            return [
                'id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'image_url' => $project->image_url ? asset('storage/' . $project->image_url) : null,
                'project_url' => $project->project_url,
                'github_url' => $project->github_url,
                'technologies' => $project->technologies ?? [],
                'completion_date' => $project->completion_date,
                'client' => $project->client,
                'featured' => $project->featured,
            ];
        });

        $data = [
            'id' => (string) $developer->id,
            'name' => $developer->name . ' ' . $developer->lastname,
            'email' => $developer->email,
            'title' => $profile?->headline ?? 'Desarrollador',
            'location' => $profile?->location ?? 'Sin ubicación',
            'hourlyRate' => $profile?->hourly_rate ?? null,
            'rating' => round($developer->reviews_received_avg_rating ?? 0, 1),
            'reviewsCount' => $developer->reviews_received_count ?? 0,
            'completedProjects' => $completedProjects,
            'completedProjectsList' => $completedProjectsList,
            'portfolioProjectsList' => $portfolioProjectsList,
            'availability' => $profile?->availability ?? 'available',
            'skills' => $profile?->skills ?? [],
            'experience' => $profile?->experience_years ?? null,
            'languages' => $profile?->languages ?? [],
            'bio' => $profile?->bio ?? '',
            'links' => $profile?->links ?? [],
            'lastActive' => $developer->updated_at?->diffForHumans(),
            'isVerified' => $developer->email_verified_at !== null,
            'joinedAt' => $developer->created_at->format('M Y'),
            'profilePicture' => $developer->profile_picture ? asset('storage/' . $developer->profile_picture) : null,
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
