<?php

namespace Database\Factories;

use App\Models\EmbedConfiguration;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmbedConfiguration>
 */
class EmbedConfigurationFactory extends Factory
{
    protected $model = EmbedConfiguration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'space_id' => Space::factory(),
            'layout' => 'masonry',
            'dark_mode' => false,
            'animation_enabled' => true,
            'background_color' => null,
            'item_limit' => 12,
            'show_rating' => true,
        ];
    }
}