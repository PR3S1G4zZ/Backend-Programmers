<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $project_id
 * @property int $developer_id
 * @property string $cover_letter
 * @property string $status
 */
class Application extends Model
{
    use HasFactory;
    protected $fillable = ['project_id','developer_id','cover_letter','status'];

    public function project(){ return $this->belongsTo(Project::class); }
    public function developer(){ return $this->belongsTo(User::class, 'developer_id');}

    
}
