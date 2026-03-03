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
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
