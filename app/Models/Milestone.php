<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Milestone extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'assigned_developer_id',
        'title',
        'description',
        'amount',
        'status', // funded, released, etc.
        'progress_status', // todo, in_progress, review, completed
        'order',
        'due_date',
        'deliverables' // JSON array
    ];

    protected $casts = [
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'deliverables' => 'array'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'assigned_developer_id');
    }
}
