<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\EasyKosController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PropertyController;
use Illuminate\Support\Facades\Route;

// Main View
Route::get('/', [EasyKosController::class, 'index'])->name('home');

// Auth Routes
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Real Data API Routes
Route::middleware('web')->group(function () {
    // Properties
    Route::get('/api/properties', [PropertyController::class, 'index']);
    Route::get('/api/my-properties', [PropertyController::class, 'myProperties']);
    Route::post('/api/properties', [PropertyController::class, 'store']);
    Route::patch('/api/properties/{id}/toggle-status', [PropertyController::class, 'toggleStatus']);

    // Chat / Messages
    Route::get('/api/chat/contacts', [ChatController::class, 'contacts']);
    Route::get('/api/chat/messages/{receiverId}', [ChatController::class, 'getMessages']);
    Route::post('/api/chat/send', [ChatController::class, 'send']);

    // Virtual Pet
    Route::get('/api/pet', [PetController::class, 'getPet']);
    Route::post('/api/pet/feed', [PetController::class, 'feed']);
    Route::post('/api/pet/play', [PetController::class, 'play']);

    // Bookings
    Route::get('/api/bookings', [BookingController::class, 'index']);
    Route::post('/api/bookings', [BookingController::class, 'store']);
});
