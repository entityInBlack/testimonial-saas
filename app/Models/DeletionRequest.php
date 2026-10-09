<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletionRequest extends Model
{
    /** @use HasFactory<\Database\Factories\DeletionRequestFactory> */
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_ACTED = 'acted';

    protected $fillable = [
        'email',
        'space_slug',
        'space_id',
        'testimonial_id',
        'status',
        'acted_at',
    ];

    protected $casts = [
        'acted_at' => 'datetime',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
