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
        // No usamos 'boolean' en casts porque interfiere con el mutador
        // El mutador se encarga de la conversión
        'views' => 'integer',
        'likes' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mutador para 'featured': Convierte valores como "0", "1", "true", "false", 0, 1 a booleano real
     * Evita errores de tipo en PostgreSQL donde la columna es boolean
     * Nota: Se ejecuta DESPUÉS del cast, por eso debemos manejar tanto strings como integers
     */
    public function setFeaturedAttribute($value)
    {
        // Si ya es booleano, usarlo directamente
        if (is_bool($value)) {
            $this->attributes['featured'] = $value;
            return;
        }
        
        // Si es integer (0 o 1), convertir a booleano
        if (is_int($value)) {
            $this->attributes['featured'] = (bool) $value;
            return;
        }
        
        // Si es string, usar filter_var
        $this->attributes['featured'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
