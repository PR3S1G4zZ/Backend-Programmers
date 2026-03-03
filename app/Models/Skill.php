<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    protected $fillable = ['name'];

    public function developers()
    {
        return $this->belongsToMany(User::class, 'developer_skill', 'skill_id', 'developer_id');
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_skill');
    }
}
