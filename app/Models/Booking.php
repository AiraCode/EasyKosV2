<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'property_id',
        'duration_months',
        'total_price',
        'discount_amount',
        'payment_method',
        'voucher_code',
        'status',
        'start_date',
    ];

    protected $casts = [
        'total_price' => 'integer',
        'discount_amount' => 'integer',
        'duration_months' => 'integer',
        'start_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
