<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;
    protected $fillable = [
        'project_id',
        'company_id',
        'developer_id',
        'rating',
        'comment',
        // Métricas de evaluación
        'clean_code_rating',
        'communication_rating',
        'compliance_rating',
        'creativity_rating',
        'post_delivery_support_rating',
    ];

    protected $uniqueConstraints = [
        'project_developer_unique' => ['project_id', 'developer_id']
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    /**
     * Calcular el promedio de todas las métricas
     */
    public function getAverageRatingAttribute()
    {
        $ratings = [
            $this->rating,
            $this->clean_code_rating,
            $this->communication_rating,
            $this->compliance_rating,
            $this->creativity_rating,
            $this->post_delivery_support_rating,
        ];
        
        return round(array_sum($ratings) / count($ratings), 1);
    }
}
