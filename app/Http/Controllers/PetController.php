<?php

namespace App\Http\Controllers;

use App\Models\Pet;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PetController extends Controller
{
    public function getPet()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $pet = Pet::firstOrCreate(
            ['user_id' => $userId],
            [
                'name' => 'Moni',
                'level' => 1,
                'exp' => 0,
                'max_exp' => 100,
                'health' => 100,
                'hunger' => 100,
                'happiness' => 100,
                'mood' => 'happy',
                'last_fed_at' => Carbon::now(),
                'last_played_at' => Carbon::now(),
            ]
        );

        $pet->syncStats();

        // Compute next feeding countdown in seconds
        $secondsSinceFed = max(0, Carbon::now()->diffInSeconds($pet->last_fed_at ?: Carbon::now()));
        $eightHours = 8 * 3600;
        $remainingSecondsToStarve = max(0, $eightHours - $secondsSinceFed);

        $hoursLeft = floor($remainingSecondsToStarve / 3600);
        $minutesLeft = floor(($remainingSecondsToStarve % 3600) / 60);

        return response()->json([
            'pet' => $pet,
            'feed_countdown' => [
                'remaining_seconds' => $remainingSecondsToStarve,
                'formatted' => $remainingSecondsToStarve > 0 ? "{$hoursLeft}j {$minutesLeft}m" : 'Sudah Lapar!',
                'last_fed_human' => $pet->last_fed_at ? $pet->last_fed_at->diffForHumans() : 'Baru saja',
                'last_played_human' => $pet->last_played_at ? $pet->last_played_at->diffForHumans() : 'Baru saja',
            ]
        ]);
    }

    public function feed()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $pet = Pet::firstOrCreate(['user_id' => $userId]);
        $pet->syncStats();

        if ($pet->hunger >= 95) {
            return response()->json([
                'success' => false,
                'pet' => $pet,
                'message' => 'Moni masih sangat kenyang! Tunggu beberapa saat sebelum memberi makan lagi. 🍏'
            ]);
        }

        // Feed pet: reset last_fed_at to now or advance hunger
        $pet->last_fed_at = Carbon::now();
        $pet->hunger = min(100, $pet->hunger + 40);
        $pet->health = min(100, $pet->health + 20);
        $pet->exp += 20;
        $pet->mood = 'happy';

        $leveledUp = false;
        if ($pet->exp >= $pet->max_exp) {
            $pet->level += 1;
            $pet->exp -= $pet->max_exp;
            $pet->max_exp += 50;
            $pet->mood = 'excited';
            $leveledUp = true;
        }

        $pet->save();
        $pet->syncStats();

        return response()->json([
            'success' => true,
            'pet' => $pet,
            'leveled_up' => $leveledUp,
            'message' => $leveledUp 
                ? "🎉 Selamat! Moni naik ke Level {$pet->level}! Voucher diskon sewa terbuka!" 
                : 'Nyam nyam! Moni merasa senang dan kenyang! 🍏 (+20 EXP & +20% Kesehatan)'
        ]);
    }

    public function play()
    {
        $userId = Auth::id();
        if (!$userId) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $pet = Pet::firstOrCreate(['user_id' => $userId]);
        $pet->syncStats();

        if ($pet->hunger <= 15) {
            $pet->mood = 'hungry';
            $pet->save();
            return response()->json([
                'success' => false,
                'pet' => $pet,
                'message' => 'Moni lemas karena kelaparan! Beri makan terlebih dahulu sebelum bermain. 🥜'
            ]);
        }

        // Play with pet
        $pet->last_played_at = Carbon::now();
        $pet->happiness = min(100, $pet->happiness + 35);
        $pet->exp += 30;
        $pet->mood = 'excited';

        $leveledUp = false;
        if ($pet->exp >= $pet->max_exp) {
            $pet->level += 1;
            $pet->exp -= $pet->max_exp;
            $pet->max_exp += 50;
            $leveledUp = true;
        }

        $pet->save();
        $pet->syncStats();

        return response()->json([
            'success' => true,
            'pet' => $pet,
            'leveled_up' => $leveledUp,
            'message' => $leveledUp 
                ? "🎉 Yey! Moni sangat gembira dan naik ke Level {$pet->level}!" 
                : 'Yey! Bermain seru bersama Moni! ⚽ (+30 EXP & Kebahagiaan 100%)'
        ]);
    }
}
