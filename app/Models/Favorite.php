<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = [
        'company_id',
        'developer_id',
    ];

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }
}
