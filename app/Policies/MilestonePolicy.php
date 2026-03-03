<?php

namespace App\Policies;

use App\Models\Milestone;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MilestonePolicy
{
    public function before(User $user, $ability)
    {
        if ($user->role === 'admin') {
            return true;
        }
    }

    public function viewAny(User $user, ?Project $project = null): bool
    {
        // If separate controller checks participation, we might just return true here 
        // but robustly: check if user is company owner or assigned developer.
        // For simplicity in index context, we often rely on Controller scoping.
        return true; 
    }

    public function view(User $user, Milestone $milestone): bool
    {
        $project = $milestone->project;
        return $user->id === $project->company_id || 
               $project->applications()->where('developer_id', $user->id)->where('status', 'accepted')->exists();
    }

    public function create(User $user, Project $project): bool
    {
        return $user->id === $project->company_id;
    }

    public function update(User $user, Milestone $milestone): bool
    {
        $project = $milestone->project;
        
        // Company can update everything
        if ($user->id === $project->company_id) {
            return true;
        }

        // Developer can only update status
        $isAssigned = $project->applications()
            ->where('developer_id', $user->id)
            ->where('status', 'accepted')
            ->exists();

        if ($isAssigned) {
            // Logic handled in Controller to restrict WHICH fields, 
            // but Policy allows "update" attempt.
            return true; 
        }

        return false;
    }

    public function delete(User $user, Milestone $milestone): bool
    {
        return $user->id === $milestone->project->company_id;
    }

    // Custom actions
    public function submit(User $user, Milestone $milestone): bool
    {
        $project = $milestone->project;
        return $project->applications()
            ->where('developer_id', $user->id)
            ->where('status', 'accepted')
            ->exists();
    }

    public function approve(User $user, Milestone $milestone): bool
    {
        return $user->id === $milestone->project->company_id;
    }

    public function reject(User $user, Milestone $milestone): bool
    {
        return $user->id === $milestone->project->company_id;
    }
}
