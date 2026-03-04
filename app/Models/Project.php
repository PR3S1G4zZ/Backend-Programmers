<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $company_id
 * @property string $title
 * @property string $description
 * @property float $budget_min
 * @property float $budget_max
 * @property string $status
 */
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use HasFactory, SoftDeletes;

    //campos asignables
    protected $fillable = [
        'company_id',
        'title',
        'description',
        'budget_min',
        'budget_max',
        'budget_type',
        'duration_value',
        'duration_unit',
        'location',
        'remote',
        'level',
        'priority',
        'featured',
        'deadline',
        'max_applicants',
        'tags',
        'status',
    ];

    protected $casts = [
        'remote' => 'boolean',
        'featured' => 'boolean',
        'deadline' => 'date',
        'tags' => 'array',
    ];

    //funciones de relacion
    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    // un proyecto tiene muchas aplicaciones
    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function milestones()
    {
        return $this->hasMany(Milestone::class);
    }

    public function categories()
    {
        return $this->belongsToMany(ProjectCategory::class, 'project_category_project');
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'project_skill');
    }

    /**
     * Obtener el progreso de cada desarrollador en el proyecto
     */
    public function developerProgress()
    {
        return $this->hasMany(DeveloperProgress::class);
    }

    /**
     * Obtener el progreso general del proyecto promediando el progreso de todos los desarrolladores
     */
    public function getOverallDeveloperProgressAttribute()
    {
        $progressRecords = $this->developerProgress;
        
        if ($progressRecords->isEmpty()) {
            return 0;
        }
        
        $totalProgress = $progressRecords->sum('progress');
        return round($totalProgress / $progressRecords->count());
    }

    /**
     * Calcular el porcentaje de progreso promedio del proyecto basado en milestones y desarrolladores
     * Retorna valor entre 0 y 100
     */
    public function getProgressPercentageAttribute()
    {
        $totalMilestones = $this->milestones()->count();
        $acceptedDevsCount = $this->applications()->where('status', 'accepted')->count();

        if ($totalMilestones === 0 || $acceptedDevsCount === 0) {
            return 0;
        }

        $totalExpectedCompletions = $totalMilestones * $acceptedDevsCount;
        
        $completedMilestones = \App\Models\DeveloperMilestone::whereIn('milestone_id', $this->milestones->pluck('id'))
            ->where('progress_status', 'completed')
            ->count();
        
        return round(($completedMilestones / $totalExpectedCompletions) * 100);
    }

    /**
     * Verificar si todas las milestones están completadas por todos los desarrolladores
     */
    public function getAllMilestonesCompletedAttribute()
    {
        $totalMilestones = $this->milestones()->count();
        $acceptedDevsCount = $this->applications()->where('status', 'accepted')->count();

        if ($totalMilestones === 0 || $acceptedDevsCount === 0) {
            return false;
        }

        $totalExpectedCompletions = $totalMilestones * $acceptedDevsCount;
        
        $completedMilestones = \App\Models\DeveloperMilestone::whereIn('milestone_id', $this->milestones->pluck('id'))
            ->where('progress_status', 'completed')
            ->count();
        
        return $completedMilestones === $totalExpectedCompletions;
    }

    /**
     * Obtener el desarrollador asignado al proyecto
     */
    public function assignedDeveloper()
    {
        $application = $this->applications()->where('status', 'accepted')->first();
        return $application ? $application->developer : null;
    }
}
