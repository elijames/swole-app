<?php

namespace Tests\Feature;

use App\Models\Exercise;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExerciseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_retrieve_exercise(): void
    {
        // Create a test exercise
        $exercise = Exercise::create([
            'name' => 'Bench Press',
            'instructions' => 'Lie on bench, lower bar to chest, push up to starting position',
            'category' => 1,
        ]);

        // Verify exercise was saved and has an ID
        $this->assertNotNull($exercise->id);

        // Retrieve the exercise from database
        $found = Exercise::find($exercise->id);

        // Verify the retrieved exercise matches what we created
        $this->assertEquals('Bench Press', $found->name);
        $this->assertEquals('Lie on bench, lower bar to chest, push up to starting position', $found->instructions);
        $this->assertEquals(1, $found->category);
    }
}
