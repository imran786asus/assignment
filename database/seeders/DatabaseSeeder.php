<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Mark;
use App\Models\Student;
use Database\Factories\MarkFactory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
         Student::factory()->count(50)->has(Mark::factory()->count(4)->sequence(
             ['subject_name' => 'Hindi'],
             ['subject_name' => 'English'],
             ['subject_name' => 'Math'],
             ['subject_name' => 'Science'])
             ->sequence(
                 ['test_date' => '2023-10-10'],
                 ['test_date' => '2023-10-11'],
                 ['test_date' => '2023-10-12'],
                 ['test_date' => '2023-10-13']))->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
