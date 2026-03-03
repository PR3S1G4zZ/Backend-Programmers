<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlatformCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'company_id',
        'developer_id',
        'total_amount',
        'held_amount',
        'commission_rate',
        'commission_amount',
        'net_amount',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'held_amount' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(User::class, 'company_id');
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'developer_id');
    }

    /**
     * Calcular la comisión basada en el monto
     */
    public static function calculateCommission(float $amount): array
    {
        $rate = $amount < 500 ? 0.20 : 0.15;
        $commission = $amount * $rate;
        $netAmount = $amount - $commission;

        return [
            'rate' => $rate,
            'commission' => $commission,
            'net_amount' => $netAmount,
        ];
    }
}
