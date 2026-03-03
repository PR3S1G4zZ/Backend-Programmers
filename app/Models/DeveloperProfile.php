<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $skills
 */
class DeveloperProfile extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'headline',
        'skills',
        'bio',
        'links',
        'location',
        'country',
        'hourly_rate',
        'availability',
        'experience_years',
        'languages',
    ];

    protected $casts = [
        'skills' => 'array',
        'links' => 'array',
        'languages' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
