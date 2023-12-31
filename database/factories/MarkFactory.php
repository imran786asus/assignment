<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Mark>
 */
class MarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => fake()->name(),
            'subject_name' => fake()->randomElement(['Hindi','English','Math','Science']),
            'marks' => mt_rand(1,100),
            'test_date' => fake()->date,
        ];
    }
}
