<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmbedConfiguration extends Model
{
    /** @use HasFactory<\Database\Factories\EmbedConfigurationFactory> */
    use HasFactory;

    protected $fillable = [
        'space_id',
        'layout',
        'dark_mode',
        'animation_enabled',
        'background_color',
        'item_limit',
        'show_rating',
    ];

    protected $casts = [
        'dark_mode' => 'boolean',
        'animation_enabled' => 'boolean',
        'show_rating' => 'boolean',
        'item_limit' => 'integer',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }
}
