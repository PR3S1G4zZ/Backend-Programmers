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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mutador para is_default: asegura que se guarde como booleano
     */
    public function setIsDefaultAttribute($value)
    {
        $this->attributes['is_default'] = $value ? true : false;
    }

    /**
     * Accessor para is_default: asegura que siempre retorne booleano
     */
    public function getIsDefaultAttribute($value)
    {
        return (bool) $value;
    }
}
