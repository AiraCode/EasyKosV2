<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'duration_months' => 'nullable|integer|min:1',
            'payment_method' => 'required|string',
            'voucher_code' => 'nullable|string',
        ]);

        $property = Property::findOrFail($request->property_id);
        $duration = $request->duration_months ?: 1;
        $totalPrice = $property->price * $duration;
        $discount = 0;

        if ($request->voucher_code) {
            $code = strtoupper(trim($request->voucher_code));
            if ($code === 'VETPET100') {
                $discount = 100000;
            } elseif ($code === 'EASYPET10') {
                $discount = (int) round($totalPrice * 0.1);
            }
        }

        $finalPrice = max(0, $totalPrice - $discount);

        $booking = Booking::create([
            'user_id' => $userId,
            'property_id' => $property->id,
            'duration_months' => $duration,
            'total_price' => $finalPrice,
            'discount_amount' => $discount,
            'payment_method' => $request->payment_method,
            'voucher_code' => $request->voucher_code,
            'status' => 'Active',
            'start_date' => now(),
        ]);

        return response()->json([
            'success' => true,
            'booking' => $booking->load('property'),
            'message' => 'Pembayaran Berhasil! Booking telah terkonfirmasi.'
        ]);
    }

    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($user->role === 'owner') {
            // Bookings on properties owned by this owner
            $propertyIds = Property::where('user_id', $user->id)->pluck('id');
            $bookings = Booking::whereIn('property_id', $propertyIds)->with(['user', 'property'])->latest()->get();
        } else {
            // Bookings made by this tenant
            $bookings = Booking::where('user_id', $user->id)->with(['property.owner'])->latest()->get();
        }

        return response()->json(['bookings' => $bookings]);
    }
}
