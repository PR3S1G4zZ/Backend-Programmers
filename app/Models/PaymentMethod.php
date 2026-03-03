<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    //
    protected $fillable = [
        'user_id',
        'type',
        'details',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        // 'details' => 'array', // Removing this to keep it as string for frontend to parse
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
