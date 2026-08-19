<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PropertyController extends Controller
{
    public function index(Request $request)
    {
        $query = Property::with('owner');

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $properties = $query->latest()->get();

        return response()->json(['properties' => $properties]);
    }

    public function myProperties()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $properties = Property::where('user_id', $userId)->latest()->get();

        return response()->json(['properties' => $properties]);
    }

    public function store(Request $request)
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string',
            'price' => 'required|numeric',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
            'facilities' => 'nullable|array',
        ]);

        $category = 'single';
        if (str_contains(strtolower($request->type), 'deluxe')) $category = 'deluxe';
        elseif (str_contains(strtolower($request->type), 'premium')) $category = 'premium';
        elseif (str_contains(strtolower($request->type), 'double')) $category = 'double';

        $property = Property::create([
            'user_id' => $userId,
            'name' => $request->name,
            'type' => $request->type,
            'category' => $category,
            'address' => $request->address ?: 'Jl. Kaliurang Km 5, Sleman, Yogyakarta',
            'price' => (int) $request->price,
            'rating' => 5.0,
            'reviews_count' => 1,
            'image' => 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
            'gallery' => [
                'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80',
                'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=800&q=80',
            ],
            'facilities' => $request->facilities ?: ['WiFi', 'Kamar Mandi Dalam', 'Kasur Springbed'],
            'description' => $request->description ?: 'Kos nyaman dengan fasilitas lengkap.',
            'status' => 'Available',
            'total_rooms' => 4,
            'occupied_rooms' => 0,
        ]);

        return response()->json([
            'success' => true,
            'property' => $property,
            'message' => 'Listing kos baru berhasil ditambahkan!'
        ]);
    }

    public function toggleStatus($id)
    {
        $userId = Auth::id();
        $property = Property::where('id', $id)->where('user_id', $userId)->firstOrFail();

        $property->status = $property->status === 'Available' ? 'Full' : 'Available';
        $property->save();

        return response()->json([
            'success' => true,
            'status' => $property->status,
            'message' => 'Status kos diubah menjadi: ' . $property->status
        ]);
    }
}
