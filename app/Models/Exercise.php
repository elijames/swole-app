<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Exercise extends Model
{
    protected $fillable = [
        'exercise_id',
        'name',
        'gif_url',
        'target_muscles',
        'body_parts',
        'equipments',
        'secondary_muscles',
        'instructions',
        'category',
    ];

    protected $casts = [
        'target_muscles' => 'array',
        'body_parts' => 'array',
        'equipments' => 'array',
        'secondary_muscles' => 'array',
        'instructions' => 'array',
        'category' => 'integer',
    ];

    // Category constants
    const CATEGORY_STRENGTH = 1;
    const CATEGORY_BODYWEIGHT = 2;
    const CATEGORY_CARDIO = 3;

    /**
     * Scope to find exercises by target muscle
     */
    public function scopeByMuscle($query, string $muscle)
    {
        return $query->whereJsonContains('target_muscles', $muscle);
    }

    /**
     * Scope to find exercises by equipment
     */
    public function scopeByEquipment($query, string $equipment)
    {
        return $query->whereJsonContains('equipments', $equipment);
    }

    /**
     * Scope to find exercises that work multiple muscles
     */
    public function scopeCompoundExercises($query, int $minMuscles = 2)
    {
        return $query->whereJsonLength('target_muscles', '>=', $minMuscles);
    }

    /**
     * Get exercises suitable for beginners (no equipment or basic equipment)
     */
    public function scopeForBeginners($query)
    {
        return $query->where(function($q) {
            $q->whereJsonContains('equipments', 'body weight')
              ->orWhereJsonContains('equipments', 'dumbbell')
              ->orWhereJsonContains('equipments', 'resistance band');
        });
    }

    /**
     * Get the category name
     */
    public function getCategoryNameAttribute(): string
    {
        return match($this->category) {
            self::CATEGORY_STRENGTH => 'Strength Training',
            self::CATEGORY_BODYWEIGHT => 'Bodyweight',
            self::CATEGORY_CARDIO => 'Cardio',
            default => 'Unknown'
        };
    }

    /**
     * Get formatted instructions with step numbers
     */
    public function getFormattedInstructionsAttribute(): array
    {
        return array_map(function($instruction) {
            // Remove "Step:X" prefix if it exists and add clean numbering
            return preg_replace('/^Step:\d+\s*/', '', $instruction);
        }, $this->instructions);
    }

    // Helper method to determine category based on equipment
    public static function determineCategory(array $equipments): int
    {
        $cardioEquipment = [
            'elliptical machine', 'treadmill', 'stationary bike',
            'stepmill machine', 'upper body ergometer'
        ];

        $strengthEquipment = [
            'barbell', 'dumbbell', 'kettlebell', 'leverage machine',
            'smith machine', 'cable', 'band', 'weighted', 'ez barbell',
            'olympic barbell', 'rope'
        ];

        foreach ($equipments as $equipment) {
            if (in_array(strtolower($equipment), $cardioEquipment)) {
                return 3; // Cardio
            }
            if (in_array(strtolower($equipment), $strengthEquipment)) {
                return 1; // Strength
            }
        }

        return 2; // Bodyweight
    }
}