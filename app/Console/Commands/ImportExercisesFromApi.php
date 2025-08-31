<?php

namespace App\Console\Commands;

use App\Models\Exercise;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ImportExercisesFromApi extends Command
{
    protected $signature = 'exercises:import 
        {--limit=2000 : Number of exercises to import}
        {--resume : Resume from last successful muscle group}
        {--retry-delay=60 : Seconds to wait after rate limit before retrying}';

    protected $description = 'Import exercises from ExerciseDB API';

    protected $muscles = [
        'abductors',
        'abs',
        'adductors',
        'biceps',
        'calves',
        'cardiovascular system',
        'delts',
        'forearms',
        'glutes',
        'hamstrings',
        'lats',
        'levator scapulae',
        'pectorals',
        'quads',
        'serratus anterior',
        'spine',
        'traps',
        'triceps',
        'upper back'
    ];

    protected $baseUrl = 'https://exercisedb.dev/api/v1';
    protected $maxRetries = 3;

    public function handle()
    {
        $this->info('Starting exercise import...');
        
        // Handle resume option
        $startIndex = 0;
        if ($this->option('resume')) {
            $lastMuscle = Cache::get('last_imported_muscle');
            if ($lastMuscle) {
                $startIndex = array_search($lastMuscle, $this->muscles) + 1;
                $this->info("Resuming from after {$lastMuscle}");
            }
        }

        try {
            $totalExercises = 0;
            $remainingMuscles = array_slice($this->muscles, $startIndex);
            $muscleBar = $this->output->createProgressBar(count($remainingMuscles));
            $muscleBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
            
            foreach ($remainingMuscles as $muscle) {
                $muscleBar->setMessage("Processing {$muscle}...");
                
                // Fetch and save exercises for this muscle with retries
                $muscleExercises = $this->fetchExercisesForMuscle($muscle);
                
                if (!empty($muscleExercises)) {
                    // Save exercises in smaller batches
                    $batches = array_chunk($muscleExercises, 50);
                    $this->info("\nSaving " . count($muscleExercises) . " {$muscle} exercises...");
                    $saveBar = $this->output->createProgressBar(count($batches));

                    DB::beginTransaction();
                    try {
                        foreach ($batches as $batch) {
                            foreach ($batch as $exerciseData) {
                                Exercise::updateOrCreate(
                                    ['exercise_id' => $exerciseData['exerciseId']],
                                    [
                                        'name' => $exerciseData['name'],
                                        'gif_url' => $exerciseData['gifUrl'],
                                        'target_muscles' => $exerciseData['targetMuscles'],
                                        'body_parts' => $exerciseData['bodyParts'],
                                        'equipments' => $exerciseData['equipments'],
                                        'secondary_muscles' => $exerciseData['secondaryMuscles'],
                                        'instructions' => $exerciseData['instructions'],
                                        'category' => Exercise::determineCategory($exerciseData['equipments']),
                                    ]
                                );
                            }
                            $saveBar->advance();
                        }
                        DB::commit();
                        
                        // Save progress
                        Cache::put('last_imported_muscle', $muscle, now()->addDays(1));
                        $totalExercises += count($muscleExercises);
                        
                        $saveBar->finish();
                        $this->newLine(2);
                    } catch (\Exception $e) {
                        DB::rollBack();
                        throw $e;
                    }
                }
                
                $muscleBar->advance();
            }

            $muscleBar->finish();
            $this->newLine(2);
            $this->info("Import completed successfully! Imported {$totalExercises} total exercises.");

            // Clear resume cache on successful completion
            Cache::forget('last_imported_muscle');

            // Show distribution statistics
            $this->showStatistics();

            return 0;

        } catch (\Exception $e) {
            $this->error('Error importing exercises: ' . $e->getMessage());
            return 1;
        }
    }

    protected function fetchExercisesForMuscle(string $muscle): array
    {
        $allExercises = [];
        $retryCount = 0;
        $retryDelay = $this->option('retry-delay');

        while ($retryCount < $this->maxRetries) {
            try {
                // Get first page
                $url = "{$this->baseUrl}/muscles/{$muscle}/exercises";
                $response = Http::get($url);
                
                if ($response->status() === 429) {
                    $retryCount++;
                    $waitTime = $retryDelay * pow(2, $retryCount - 1);
                    $this->warn("\nRate limit hit, waiting {$waitTime} seconds before retry {$retryCount}/{$this->maxRetries}...");
                    sleep($waitTime);
                    continue;
                }
                
                if (!$response->successful()) {
                    $this->error("\nFailed to fetch exercises for {$muscle}: " . $response->status());
                    return [];
                }

                $data = $response->json();
                $metadata = $data['metadata'];
                $muscleExercises = $data['data'];
                
                $this->info("\nFound {$metadata['totalExercises']} {$muscle} exercises across {$metadata['totalPages']} pages");

                // Get next pages if available
                $nextPage = $metadata['nextPage'];
                $currentPage = 1;

                $pageBar = $this->output->createProgressBar($metadata['totalPages']);
                $pageBar->setFormat(' Page %current%/%max% [%bar%] %percent:3s%%');
                $pageBar->advance();

                while ($nextPage && $currentPage < $metadata['totalPages']) {
                    $response = Http::get($nextPage);
                    
                    if ($response->status() === 429) {
                        $retryCount++;
                        $waitTime = $retryDelay * pow(2, $retryCount - 1);
                        $this->warn("\nRate limit hit, waiting {$waitTime} seconds before retry {$retryCount}/{$this->maxRetries}...");
                        sleep($waitTime);
                        continue;
                    }
                    
                    if (!$response->successful()) {
                        $this->error("\nFailed to fetch page " . ($currentPage + 1) . " for {$muscle}");
                        break;
                    }

                    $data = $response->json();
                    $muscleExercises = array_merge($muscleExercises, $data['data']);
                    $nextPage = $data['metadata']['nextPage'];
                    $currentPage++;
                    $pageBar->advance();
                    
                    // Add a base delay between requests
                    sleep(2);
                }

                $pageBar->finish();
                return $muscleExercises;

            } catch (\Exception $e) {
                $retryCount++;
                if ($retryCount >= $this->maxRetries) {
                    throw $e;
                }
                $waitTime = $retryDelay * pow(2, $retryCount - 1);
                $this->warn("\nError occurred, waiting {$waitTime} seconds before retry {$retryCount}/{$this->maxRetries}...");
                sleep($waitTime);
            }
        }

        return [];
    }

    protected function showStatistics(): void
    {
        $this->info("\nExercise distribution by category:");
        $categories = DB::table('exercises')
            ->select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->get();

        foreach ($categories as $category) {
            $categoryName = match($category->category) {
                1 => 'Strength Training',
                2 => 'Bodyweight',
                3 => 'Cardio',
                default => 'Unknown'
            };
            $this->info("- {$categoryName}: {$category->count} exercises");
        }

        $this->info("\nExercise distribution by target muscle:");
        $exercises = Exercise::all();
        $muscleCounts = [];
        foreach ($exercises as $exercise) {
            foreach ($exercise->target_muscles as $muscle) {
                $muscleCounts[$muscle] = ($muscleCounts[$muscle] ?? 0) + 1;
            }
        }
        arsort($muscleCounts);
        foreach ($muscleCounts as $muscle => $count) {
            $this->info("- {$muscle}: {$count} exercises");
        }

        $this->info("\nExercise distribution by equipment:");
        $equipmentCounts = [];
        foreach ($exercises as $exercise) {
            foreach ($exercise->equipments as $equipment) {
                $equipmentCounts[$equipment] = ($equipmentCounts[$equipment] ?? 0) + 1;
            }
        }
        arsort($equipmentCounts);
        foreach ($equipmentCounts as $equipment => $count) {
            $this->info("- {$equipment}: {$count} exercises");
        }
    }
}