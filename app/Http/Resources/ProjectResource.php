<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'company' => new UserResource($this->whenLoaded('company')),
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'budget_type' => $this->budget_type,
            'status' => $this->status,
            'duration_value' => $this->duration_value,
            'duration_unit' => $this->duration_unit,
            'location' => $this->location,
            'remote' => $this->remote,
            'level' => $this->level,
            'priority' => $this->priority,
            'featured' => $this->featured,
            'deadline' => $this->deadline,
            'max_applicants' => $this->max_applicants,
            'tags' => $this->tags ?? [],
            'image_url' => $this->image_url,
            'categories' => $this->whenLoaded('categories', function() {
                return $this->categories->map(function($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                    ];
                });
            }),
            'skills' => $this->whenLoaded('skills', function() {
                return $this->skills->map(function($skill) {
                    return [
                        'id' => $skill->id,
                        'name' => $skill->name,
                    ];
                });
            }),
            'applications_count' => $this->whenCounted('applications'),
            'has_applied' => $this->when(isset($this->has_applied), $this->has_applied),
            // Información de progreso del proyecto
            'progress_percentage' => $this->progress_percentage,
            'developer_progress' => $request->has('developer_id') 
                ? $this->getDeveloperProgress($request->input('developer_id'))
                : ($request->user() && $request->user()->user_type === 'programmer' 
                    ? $this->getDeveloperProgress($request->user()->id) 
                    : null),
            'all_milestones_completed' => $this->all_milestones_completed,
            'milestones_count' => $this->whenCounted('milestones'),
            'completed_milestones_count' => $this->when(isset($this->completed_milestones_count), $this->completed_milestones_count),
            'created_at' => $this->created_at,
            // Agregar aplicaciones aceptadas con información del developer
            'applications' => $this->whenLoaded('applications', function() {
                return $this->applications
                    ->where('status', 'accepted')
                    ->map(function($app) {
                        return [
                            'id' => $app->id,
                            'developer' => $app->developer ? [
                                'id' => $app->developer->id,
                                'name' => $app->developer->name,
                                'email' => $app->developer->email,
                            ] : null,
                            'status' => $app->status,
                        ];
                    })->values()->all(); // Convert to array
            }),
        ];
    }
}
