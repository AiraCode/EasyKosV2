<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pet extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'level',
        'exp',
        'max_exp',
        'health',
        'hunger',
        'happiness',
        'mood',
        'last_fed_at',
        'last_played_at',
    ];

    protected $casts = [
        'level' => 'integer',
        'exp' => 'integer',
        'max_exp' => 'integer',
        'health' => 'integer',
        'hunger' => 'integer',
        'happiness' => 'integer',
        'last_fed_at' => 'datetime',
        'last_played_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Real-time calculation based on exact timestamps stored in database.
     * - Fullness (Hunger): 100% depletes in 8 hours (3 meals/day).
     * - Starvation (Health): If hunger reaches 0%, health decreases to 0% over the next 8 hours.
     * - Happiness: Depletes to 0% over 5 days (120 hours) if not played with.
     */
    public function syncStats()
    {
        $now = Carbon::now();

        // 1. Calculate Hunger and Health
        if (!$this->last_fed_at) {
            $this->last_fed_at = $now;
        }

        $secondsSinceFed = max(0, $now->diffInSeconds($this->last_fed_at));
        $eightHoursSeconds = 8 * 3600; // 28,800 seconds

        if ($secondsSinceFed <= $eightHoursSeconds) {
            // Still within 8 hours: hunger decreases proportionally from 100%
            $this->hunger = (int) round(100 - (($secondsSinceFed / $eightHoursSeconds) * 100));
            $this->health = 100;
        } else {
            // Hunger is completely 0% (Starving)
            $this->hunger = 0;

            // Extra time beyond 8 hours consumes health
            $starvingSeconds = $secondsSinceFed - $eightHoursSeconds;
            $healthLost = ($starvingSeconds / $eightHoursSeconds) * 100;
            $this->health = max(0, (int) round(100 - $healthLost));
        }

        // 2. Calculate Happiness
        if (!$this->last_played_at) {
            $this->last_played_at = $now;
        }

        $secondsSincePlayed = max(0, $now->diffInSeconds($this->last_played_at));
        $fiveDaysSeconds = 5 * 24 * 3600; // 432,000 seconds (120 hours)

        $happinessLost = ($secondsSincePlayed / $fiveDaysSeconds) * 100;
        $this->happiness = max(0, min(100, (int) round(100 - $happinessLost)));

        // 3. Determine dynamic mood expression
        if ($this->hunger < 25) {
            $this->mood = 'hungry';
        } elseif ($this->health < 40 || $this->happiness < 30) {
            $this->mood = 'sleepy';
        } elseif ($this->mood !== 'excited') {
            $this->mood = 'happy';
        }

        $this->save();
        return $this;
    }
}
