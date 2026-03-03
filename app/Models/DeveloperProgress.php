<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeveloperProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'developer_id',
        'progress',
        'milestones_completed',
        'tasks_completed'
    ];

    protected $casts = [
        'milestones_completed' => 'array',
        'tasks_completed' => 'array',
        'progress' => 'integer'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    /**
     * Calcular el progreso del desarrollador en base a los milestones completados
     */
    public function calculateProgressFromMilestones()
    {
        $project = $this->project;
        $totalMilestones = $project->milestones()->count();
        
        if ($totalMilestones === 0) {
            return 0;
        }
        
        $completedMilestones = count($this->milestones_completed ?? []);
        return round(($completedMilestones / $totalMilestones) * 100);
    }
}