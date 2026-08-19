<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'category',
        'address',
        'price',
        'rating',
        'reviews_count',
        'image',
        'gallery',
        'facilities',
        'description',
        'status',
        'total_rooms',
        'occupied_rooms',
    ];

    protected $casts = [
        'gallery' => 'array',
        'facilities' => 'array',
        'rating' => 'float',
        'price' => 'integer',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
