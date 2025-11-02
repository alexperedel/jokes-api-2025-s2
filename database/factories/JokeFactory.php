<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Joke>
 */
class JokeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $jokeTemplates = [
            [
                'title' => 'Programming Joke',
                'content' => 'Why do programmers prefer dark mode? Because light attracts bugs!',
            ],
            [
                'title' => 'SQL Joke',
                'content' => 'A SQL query walks into a bar, walks up to two tables and asks... "Can I join you?"',
            ],
            [
                'title' => 'Array Joke',
                'content' => 'Why did the developer go broke? Because he used up all his cache!',
            ],
            [
                'title' => 'Java Joke',
                'content' => 'How do you comfort a JavaScript bug? You console it!',
            ],
            [
                'title' => 'Python Joke',
                'content' => 'Why do Python programmers wear glasses? Because they can\'t C#!',
            ],
            [
                'title' => 'Dad Joke',
                'content' => 'Why don\'t scientists trust atoms? Because they make up everything!',
            ],
            [
                'title' => 'Knock Knock',
                'content' => 'Knock knock. Who\'s there? Interrupting cow. Interrupting cow w- MOOOOO!',
            ],
        ];

        $joke = fake()->randomElement($jokeTemplates);

        return [
            'title' => $joke['title'] . ' #' . fake()->unique()->numberBetween(1, 10000),
            'content' => $joke['content'],
            'user_id' => User::factory(),
            'published_at' => fake()->boolean(70) ? fake()->dateTimeBetween('-1 year', 'now') : null,
        ];
    }

    /**
     * Indicate that the joke is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ]);
    }

    /**
     * Indicate that the joke is unpublished.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => null,
        ]);
    }

    /**
     * Create a joke with specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }
}
