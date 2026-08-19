<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Pet;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EasyKosController extends Controller
{
    public function index()
    {
        $properties = Property::with('owner')->latest()->get();

        $user = Auth::user();
        $myProperties = [];
        $pet = null;
        $bookings = [];

        if ($user) {
            $pet = Pet::firstOrCreate(['user_id' => $user->id]);

            if ($user->role === 'owner') {
                $myProperties = Property::where('user_id', $user->id)->latest()->get();
                $propIds = $myProperties->pluck('id');
                $bookings = Booking::whereIn('property_id', $propIds)->with(['user', 'property'])->latest()->get();
            } else {
                $bookings = Booking::where('user_id', $user->id)->with(['property.owner'])->latest()->get();
            }
        }

        return view('easykos.index', compact('properties', 'myProperties', 'pet', 'bookings'));
    }
}
