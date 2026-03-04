<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeveloperMilestone extends Model
{
    use HasFactory;

    protected $table = 'developer_milestone';

    protected $fillable = [
        'milestone_id',
        'developer_id',
        'progress_status', // todo, in_progress, review, completed
        'deliverables' // JSON array
    ];

    protected $casts = [
        'deliverables' => 'array',
    ];

    public function milestone()
    {
        return $this->belongsTo(Milestone::class);
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }
}
