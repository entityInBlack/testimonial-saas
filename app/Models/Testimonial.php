<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Testimonial extends Model
{
    /** @use HasFactory<\Database\Factories\TestimonialFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'space_id',
        'name',
        'email',
        'address',
        'company_name',
        'social_url',
        'profile_photo',
        'testimonial',
        'rating',
        'consent_given',
        'consented_at',
        'consent_text_version',
        'is_favorite',
        'is_wall_of_love',
        'is_hidden',
        'submitted_at',
    ];

    protected $casts = [
        'consent_given' => 'boolean',
        'is_favorite' => 'boolean',
        'is_wall_of_love' => 'boolean',
        'is_hidden' => 'boolean',
        'consented_at' => 'datetime',
        'submitted_at' => 'datetime',
        // `is_public` is a STORED generated column on MySQL — never written from PHP.
        // MySQL itself rejects writes to a generated column, so no PHP-side
        // override is needed (and adding one would silently swallow a programmer
        // error instead of surfacing it).
    ];

    /**
     * Scope used by the wall, the embed, and the public API.
     * Reads the indexed `is_public` STORED column directly — the four
     * conditions (consent_given, is_wall_of_love, is_hidden, deleted_at)
     * are evaluated by MySQL on every write.
     */
    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query->where('is_public', 1);
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    /**
     * Photo URL on the `public` disk. Returns null when no photo is attached.
     * The storage path is the only thing stored in the DB; the URL is
     * derived so a future `cdn.example.com` change is one line.
     */
    public function photoUrl(): ?string
    {
        if (! $this->profile_photo) {
            return null;
        }

        return Storage::disk('public')->url($this->profile_photo);
    }
}
