<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Whitelist of fields exposed by the public embed endpoint.
 *
 * Hard rule 10 (build order): only `name`, `testimonial`, `rating`
 * (only when `show_rating=true`), `submitted_at`, `company_name`,
 * `social_url`, `photo_url`. NEVER `email`, `address`, or internal ids.
 *
 * Photo display depends ONLY on `profile_photo IS NOT NULL` (data-model
 * §3.4). A later `field_config` change that disables `profile_photo`
 * on the Space does NOT hide existing photos (PRD §8 acceptance
 * criteria: "existing photos still show on the embed if `profile_photo`
 * is later disabled in `field_config`"). The resource reads
 * `profile_photo` directly off the model.
 *
 * `rating` is OMITTED entirely from the response (not null) when
 * `show_rating` is false. Hard rule 9 says the omission is server-side
 * — never rely on the client to hide it.
 *
 * `social_url` is included only when its scheme is http or https
 * (parsed, not a prefix string match). `javascript:`, `data:`,
 * `file:`, `ftp:`, etc. are normalised to null at the API. The
 * client (embed.js) ALSO checks the parsed scheme before rendering
 * a link, as defence in depth.
 */
class EmbedTestimonialResource extends JsonResource
{
    public function __construct(
        mixed $resource,
        public readonly bool $showRating,
    ) {
        parent::__construct($resource);
    }

    /**
     * Return the value iff it parses as an absolute http(s) URL.
     * The scheme check is case-insensitive (URL parsing lowercases
     * it). Any other scheme — javascript:, data:, file:, ftp:,
     * and the empty string — yields null.
     */
    protected static function safeSocialUrl(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }
        $parts = parse_url($value);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }
        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        $payload = [
            'name'         => (string) $row->name,
            'testimonial'  => (string) $row->testimonial,
            'submitted_at' => optional($row->submitted_at)->toIso8601String(),
            'company_name' => $row->company_name,
            'social_url'   => self::safeSocialUrl($row->social_url),
            'photo_url'    => $row->profile_photo
                ? Storage::disk('public')->url($row->profile_photo)
                : null,
        ];

        if ($this->showRating) {
            $payload['rating'] = $row->rating;
        }

        return $payload;
    }
}
