<?php

namespace Database\Factories;

use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Space>
 */
class SpaceFactory extends Factory
{
    protected $model = Space::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company().' Wall';

        return [
            'user_id' => User::factory(),
            'name' => $name,
            'slug' => Str::lower(Str::random(10)),
            'public_id' => Str::lower(Str::random(12)),
            'title' => $this->faker->sentence(4),
            'subtitle' => $this->faker->sentence(6),
            'ask' => $this->faker->sentence(10),
            'theme' => $this->faker->randomElement(['minimal_light', 'minimal_dark', 'soft_color']),
            'rating_enabled' => true,
            'field_config' => [
                'company_name' => ['enabled' => true, 'required' => false],
                'social_url' => ['enabled' => false, 'required' => false],
                'profile_photo' => ['enabled' => false, 'required' => false],
            ],
        ];
    }
}
