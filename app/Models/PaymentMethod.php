<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'details',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            // No usamos cast 'boolean' aquí para evitar conflicto con el mutador
            // El mutador setIsDefaultAttribute maneja toda la conversión
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mutador para 'is_default': Convierte valores como "0", "1", "true", "false", 0, 1 a booleano real
     * Evita errores de tipo en PostgreSQL donde la columna es boolean
     */
    public function setIsDefaultAttribute($value)
    {
        if (is_bool($value)) {
            $this->attributes['is_default'] = $value;
            return;
        }
        
        if (is_int($value)) {
            $this->attributes['is_default'] = (bool) $value;
            return;
        }
        
        $this->attributes['is_default'] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
