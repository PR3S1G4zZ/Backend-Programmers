<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'theme',
        'language',
        'accent_color',
        'two_factor_enabled',
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
    ];

    /**
     * Mutator para two_factor_enabled para asegurar que se envíe como string 'true'/'false'
     * Esto evita que PDO (con emulación) lo convierta a 0/1, lo cual falla en PostgreSQL.
     */
    protected function twoFactorEnabled(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            set: fn ($value) => $value ? 'true' : 'false',
        );
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
