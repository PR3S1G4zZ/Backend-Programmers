<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'lastname' => $this->lastname,
            'email' => $this->email,
            'role' => $this->role, // user_type
            'avatar' => $this->avatar(), // helper or attribute
            'rating' => $this->rating(), // helper or attribute
            'wallet' => $this->whenLoaded('wallet', function () {
                return [
                    'balance' => $this->wallet->balance,
                ];
            }),
            'profile' => $this->when($this->role === 'programmer', function () {
                return $this->developerProfile; // or specific resource
            }, function () {
                return $this->companyProfile;
            }),
        ];
    }
    
    // Helpers (assuming they don't exist in model yet, or will be added)
    protected function avatar() {
        // Fallback logic
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name . ' ' . $this->lastname);
    }

    protected function rating() {
        return 0; // Placeholder or calculate
    }
}
