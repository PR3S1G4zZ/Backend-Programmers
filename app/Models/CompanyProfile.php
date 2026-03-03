<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 */
class CompanyProfile extends Model
{
    protected $fillable = [
        'user_id',
        'company_name',
        'website',
        'about',
        'location',
        'country',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
