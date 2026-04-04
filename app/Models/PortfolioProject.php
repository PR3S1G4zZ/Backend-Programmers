<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PortfolioProject extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'image_url',
        'project_url',
        'github_url',
        'technologies',
        'completion_date',
        'client',
        'featured',
        'views',
        'likes',
    ];

    protected $casts = [
        'technologies' => 'array',
        'featured' => 'boolean',
        'views' => 'integer',
        'likes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mutador para 'featured': Convierte valores como "0", "1", "true", "false" a booleano real
     * Evita errores de tipo en PostgreSQL donde la columna es boolean
     */
    public function setFeaturedAttribute($value)
    {
        $this->attributes['featured'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
