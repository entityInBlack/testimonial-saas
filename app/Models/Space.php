<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Space extends Model
{
    /** @use HasFactory<\Database\Factories\SpaceFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Reserved slug list (PRD §3.2 / build-order Step 2). These cannot be
     * claimed by a Space — they protect app routes under `/`, `/login`,
     * `/register`, etc. from being shadowed by a public `/s/{slug}` page.
     */
    public const RESERVED_SLUGS = [
        's', 'api', 'admin', 'login', 'register', 'dashboard',
        'billing', 'embed', 'assets', 'up',
    ];

    /**
     * Maximum length of `slug` — matches the column width in the
     * `create_spaces_table` migration (`varchar(60)`).
     */
    public const SLUG_MAX_LENGTH = 60;

    /**
     * Default `field_config` shape for a new Space (PRD §3.3). Only
     * `company_name` is enabled out of the box; the other two optional
     * fields are off. The locked fields (name, email, address) are NOT
     * stored in `field_config` — they are columns on `testimonials`.
     */
    public static function defaultFieldConfig(): array
    {
        return [
            'company_name' => ['enabled' => true,  'required' => false],
            'social_url'   => ['enabled' => false, 'required' => false],
            'profile_photo' => ['enabled' => false, 'required' => false],
        ];
    }

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'public_id',
        'title',
        'subtitle',
        'ask',
        'theme',
        'rating_enabled',
        'field_config',
    ];

    protected $casts = [
        'rating_enabled' => 'boolean',
        'field_config'   => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Space $space) {
            // public_id is generated on insert and is IMMUTABLE afterwards.
            // There is no setter exposed (see boot rules in Space model).
            if (empty($space->public_id)) {
                $space->public_id = self::generatePublicId();
            }

            // field_config must always be the default shape unless the
            // caller explicitly provided one.
            if (empty($space->field_config)) {
                $space->field_config = self::defaultFieldConfig();
            }
        });
    }

    /**
     * Public, immutable identifier used in the embed URL
     * (`/api/spaces/{public_id}/testimonials`). Never reused, never rotated —
     * changing it would break every embed a customer has shipped.
     */
    public static function generatePublicId(): string
    {
        // 12-char lowercase base62 (a-z, 0-9) — ~2.2e21 keyspace, plenty for v1.
        return Str::lower(Str::random(12));
    }

    /**
     * Block ANY change to `public_id` after the row has been persisted.
     * The only place public_id is set is the `creating` model event above.
     * PRD §3.2 / build-order Step 2: "immutable (no setter exposed)".
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'public_id' && $this->exists && $value !== $this->getRawOriginal('public_id')) {
            throw new \LogicException('public_id is immutable once a Space is created.');
        }

        return parent::setAttribute($key, $value);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Alias of `owner()` so Laravel's Factory BelongsTo auto-resolution
     * can find a `user()` method on the model (Laravel looks for
     * `{foreignKey}_snake` -> `{camel}()`). The model field is
     * `user_id`, so the matching method name is `user()`.
     */
    public function user(): BelongsTo
    {
        return $this->owner();
    }

    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    public function embedConfiguration(): HasOne
    {
        return $this->hasOne(EmbedConfiguration::class);
    }

    public function deletionRequests(): HasMany
    {
        return $this->hasMany(DeletionRequest::class);
    }

    /**
     * LIVE Spaces only — soft-deleted (and tombstoned) rows are excluded.
     * This is the scope every cap check, dashboard counter, and inbox
     * dropdown MUST use. Soft-deleted Spaces do not count toward
     * `config('limits.max_spaces')` (PRD §11).
     */
    public function scopeLive(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Soft-deleted Spaces that can still be restored (within the purge
     * retention window). Tombstoned Spaces (older than `retention_days`)
     * are NOT returned by this scope.
     */
    public function scopeRecentlyDeleted(Builder $query): Builder
    {
        $cutoff = now()->subDays((int) config('purge.retention_days', 30));

        return $query->onlyTrashed()
            ->where('deleted_at', '>=', $cutoff);
    }

    /**
     * Whether this Space is a tombstone (soft-deleted past retention).
     * Tombstoned Spaces are kept in the table to claim the slug forever
     * but cannot be restored.
     */
    public function isTombstoned(): bool
    {
        if ($this->deleted_at === null) {
            return false;
        }

        $cutoff = now()->subDays((int) config('purge.retention_days', 30));

        return $this->deleted_at->lt($cutoff);
    }
}
