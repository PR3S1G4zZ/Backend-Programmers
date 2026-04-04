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

    public function setIsDefaultAttribute($value)
    {
        $this->attributes['is_default'] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
    }

    public function getIsDefaultAttribute($value)
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
