<?php

namespace Database\Seeders;

use App\Models\Exercise;
use Illuminate\Database\Seeder;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            [
                'name' => 'Barbell Bench Press',
                'instructions' => 'Lie on a flat bench with feet flat on the ground. Grip the barbell slightly wider than shoulder width. Unrack the bar, lower it to your chest with control, then press back up to starting position.',
                'category' => 1, // Strength Training
            ],
            [
                'name' => 'Squats',
                'instructions' => 'Stand with feet shoulder-width apart. Lower your body by bending knees and hips, keeping your back straight. Lower until thighs are parallel to ground, then return to standing.',
                'category' => 1, // Strength Training
            ],
            [
                'name' => 'Pull-ups',
                'instructions' => 'Hang from a pull-up bar with hands slightly wider than shoulders. Pull yourself up until your chin clears the bar, then lower back down with control.',
                'category' => 2, // Bodyweight
            ],
            [
                'name' => 'Running',
                'instructions' => 'Start at a comfortable pace. Keep your upper body relaxed, arms at 90 degrees, and land mid-foot. Maintain steady breathing.',
                'category' => 3, // Cardio
            ],
            [
                'name' => 'Deadlift',
                'instructions' => 'Stand with feet hip-width apart, barbell over mid-foot. Hinge at hips to grip bar, keep back straight. Drive through heels to lift bar, keeping it close to body.',
                'category' => 1, // Strength Training
            ],
        ];

        foreach ($exercises as $exercise) {
            Exercise::create($exercise);
        }
    }
}
