<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectCategory extends Model
{
    protected $fillable = ['name', 'description', 'color', 'icon'];

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_category_project');
    }
}
