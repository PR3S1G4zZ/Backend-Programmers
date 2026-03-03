<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'project' => new ProjectResource($this->whenLoaded('project')),
            'developer' => new UserResource($this->whenLoaded('developer')),
            'cover_letter' => $this->cover_letter,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}
