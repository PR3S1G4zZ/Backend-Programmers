<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
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
            'project_id' => $this->project_id,
            'company_id' => $this->company_id,
            'developer_id' => $this->developer_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            // Métricas de evaluación
            'clean_code_rating' => $this->clean_code_rating,
            'communication_rating' => $this->communication_rating,
            'compliance_rating' => $this->compliance_rating,
            'creativity_rating' => $this->creativity_rating,
            'post_delivery_support_rating' => $this->post_delivery_support_rating,
            // Promedio general
            'average_rating' => $this->average_rating,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relaciones
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'title' => $this->project->title,
                ];
            }),
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                    'lastname' => $this->company->lastname,
                ];
            }),
            'developer' => $this->whenLoaded('developer', function () {
                return [
                    'id' => $this->developer->id,
                    'name' => $this->developer->name,
                    'lastname' => $this->developer->lastname,
                ];
            }),
        ];
    }
}
