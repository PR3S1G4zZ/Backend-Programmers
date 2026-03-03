<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Milestone extends Model
{
    protected $fillable = [
        'project_id',
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
}
