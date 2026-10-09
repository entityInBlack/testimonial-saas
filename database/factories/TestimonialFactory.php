<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Testimonial>
 */
class TestimonialFactory extends Factory
{
    protected $model = Testimonial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->streetAddress(),
            'company_name' => $this->faker->company(),
            'social_url' => 'https://example.test/@'.$this->faker->userName(),
            'profile_photo' => null,
            'testimonial' => $this->faker->sentences(3, true),
            'rating' => $this->faker->numberBetween(3, 5),
            'consent_given' => true,
            'consented_at' => now(),
            'consent_text_version' => config('consent.current', 'v1'),
            'is_favorite' => false,
            'is_wall_of_love' => true,
            'is_hidden' => false,
            'submitted_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'is_wall_of_love' => false,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn () => [
            'is_hidden' => true,
        ]);
    }

    public function favorite(): static
    {
        return $this->state(fn () => [
            'is_favorite' => true,
        ]);
    }

    public function noConsent(): static
    {
        return $this->state(fn () => [
            'consent_given' => false,
            'consented_at' => null,
            'consent_text_version' => null,
        ]);
    }
}
