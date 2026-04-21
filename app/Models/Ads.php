<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Ads extends Model
{
    protected $table = 'ads';

    /**
     * Mass assignable fields
     */
    protected $fillable = [
        'user_id',
        'ads_id',
        'pair',
        'price_type',
        'price',
        'premium',
        'min_amount',
        'max_amount',
        'remark',
        'action_type',
        'quantity',
        'payment_period',
        'payment_methods',
        'trading_preference_set',
    ];

    /**
     * Cast JSON + numeric fields properly
     */
    protected $casts = [
        'price' => 'decimal:8',
        'premium' => 'decimal:4',
        'min_amount' => 'decimal:8',
        'max_amount' => 'decimal:8',

        'payment_methods' => 'array',
        'trading_preference_set' => 'array',
    ];

    /**
     * Auto-generate ads_id if not provided
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ad) {
            if (empty($ad->ads_id)) {
                $ad->ads_id = 'ADS_' . time() . rand(100, 999);
            }
        });
    }

    /**
     * Relationship: Ad belongs to a User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}