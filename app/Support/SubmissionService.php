<?php

namespace App\Support;

use App\Exceptions\PhotoRejectedException;
use App\Exceptions\SubmissionThrottledException;
use App\Models\Space;
use App\Models\Testimonial;
use App\Rules\Honeypot;
use App\Rules\RequiredWhenFieldConfig;
use App\Rules\ZeroWidthFreeText;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates the public /s/{slug} submission. Pure PHP, no Livewire
 * coupling — the Livewire component just calls `store()` and renders
 * the result. This separation makes the full pipeline unit-testable
 * without booting a browser or faking a Livewire cycle.
 *
 * Pipeline (PRD §6 / build order Step 3 / design.md):
 *   1. Validate the payload via a single Validator.
 *   2. Honeypot check — silently no-op if non-empty.
 *   3. Dedupe (5-min window) — silently no-op if duplicated.
 *   4. Lock check — silently no-op if at cap.
 *   5. Throttle (per-IP and per-email) — reject the 6th attempt.
 *   6. Photo pipeline (Hard Rule 13) — if a photo is present.
 *   7. Insert the row (STORED `is_public` recomputes via the DB).
 *      Roll back the photo file if insert throws.
 *
 * Return shape:
 *   ['ok' => true,  'testimonial' => Testimonial, 'thanks' => string]
 *   ['ok' => false, 'reason' => 'duplicate'|'throttled'|'locked'|'honeypot',
 *                        'thanks' => string]   // silent no-op with thanks
 *   ['ok' => false, 'reason' => 'invalid', 'errors' => MessageBag]
 *   ['ok' => false, 'reason' => 'photo_rejected', 'message' => string]
 */
class SubmissionService
{
    public function __construct(
        private PhotoProcessor $photos,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function store(Space $space, array $input, Request $request): array
    {
        // 1) validate
        $validator = $this->makeValidator($space, $input);
        if ($validator->fails()) {
            return [
                'ok' => false,
                'reason' => 'invalid',
                'errors' => $validator->errors(),
            ];
        }

        $data = $validator->validated();
        $email = strtolower((string) $data['email']);
        // Strip zero-width characters before persisting so junk in the
        // middle of a legitimate message doesn't survive.
        $text = (string) preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', (string) $data['testimonial']);
        $text = trim($text);

        // 2) honeypot — silent no-op
        if (! empty($data['website'] ?? null)) {
            return [
                'ok' => false,
                'reason' => 'honeypot',
                'thanks' => $this->pickThanks(),
            ];
        }

        // 3) dedupe (5-min window per space)
        $duplicated = Testimonial::where('space_id', $space->id)
            ->where('email', $email)
            ->where('testimonial', $text)
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($duplicated) {
            return [
                'ok' => false,
                'reason' => 'duplicate',
                'thanks' => $this->pickThanks(),
            ];
        }

        // 4) lock
        $cap = (int) config('limits.max_testimonials_per_space');
        $liveCount = $space->testimonials()->count(); // SoftDeletes filters
        if ($liveCount >= $cap) {
            return [
                'ok' => false,
                'reason' => 'locked',
                'thanks' => $this->pickThanks(),
            ];
        }

        // 5) throttle
        $ipKey = $this->ipKey($request, $space);
        $emailKey = $this->emailKey($email, $request, $space);

        if (RateLimiter::tooManyAttempts($ipKey, 5)
            || RateLimiter::tooManyAttempts($emailKey, 5)) {
            throw new SubmissionThrottledException();
        }

        // Hit BEFORE the insert so a partial-failure still counts.
        RateLimiter::hit($ipKey, 60 * 60);
        RateLimiter::hit($emailKey, 60 * 60);

        // 6) photo (optional) — accept either the request file (classic
        // upload) or the camelCase entry on the input payload (the
        // Livewire component passes a TemporaryUploadedFile through the
        // payload after WithFileUploads has bound it).
        $photoPath = null;
        $photo = $input['profilePhoto'] ?? $request->file('profilePhoto') ?? $request->file('profile_photo');
        if ($photo instanceof UploadedFile) {
            $photoPath = $this->photos->handle($photo);
        }

        // 7) insert (with photo rollback on failure)
        try {
            // The validator's rule keys are camelCase to match the Livewire
            // component property names, so $data uses camelCase too.
            $consent = (bool) ($data['consentGiven'] ?? false);
            $testimonial = Testimonial::create([
                'space_id' => $space->id,
                'name' => $data['name'],
                'email' => $email,
                'address' => $data['address'],
                'company_name' => $data['companyName'] ?? null,
                'social_url' => $data['socialUrl'] ?? null,
                'profile_photo' => $photoPath,
                'testimonial' => $text,
                'rating' => $data['rating'] ?? null,
                'consent_given' => $consent,
                'consented_at' => $consent ? now() : null,
                'consent_text_version' => $consent ? config('consent.current') : null,
                'is_favorite' => false,
                'is_wall_of_love' => false,
                'is_hidden' => false,
                'submitted_at' => now(),
            ]);
        } catch (\Throwable $e) {
            if ($photoPath !== null) {
                $this->photos->rollback($photoPath);
            }

            throw $e;
        }

        return [
            'ok' => true,
            'testimonial' => $testimonial,
            'thanks' => $this->pickThanks(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function makeValidator(Space $space, array $input): \Illuminate\Contracts\Validation\Validator
    {
        $fc = $space->field_config ?? Space::defaultFieldConfig();

        // Optional fields: Laravel's `nullable` short-circuits the rest
        // of the rule list when the value is null, which means the
        // required check would never run. So we EITHER use
        // `nullable|string|...` (no required check; value may be empty)
        // OR we use `required|string|...` (the rule itself decides
        // whether the field is actually required for this Space).
        $companyConfig = $fc['company_name'] ?? [];
        $companyEnabled = (bool) ($companyConfig['enabled'] ?? false);
        $companyRequired = $companyEnabled && (bool) ($companyConfig['required'] ?? false);
        $companyRules = $companyEnabled
            ? ($companyRequired
                ? [$this->requiredIf($companyConfig), 'string', 'max:160']
                : ['nullable', 'string', 'max:160'])
            : [];

        $socialConfig = $fc['social_url'] ?? [];
        $socialEnabled = (bool) ($socialConfig['enabled'] ?? false);
        $socialRequired = $socialEnabled && (bool) ($socialConfig['required'] ?? false);
        $socialRules = $socialEnabled
            ? ($socialRequired
                ? [$this->requiredIf($socialConfig), 'string', 'url', 'max:255']
                : ['nullable', 'string', 'url', 'max:255'])
            : [];

        $photoConfig = $fc['profile_photo'] ?? [];
        $photoEnabled = (bool) ($photoConfig['enabled'] ?? false);
        $photoRequired = $photoEnabled && (bool) ($photoConfig['required'] ?? false);
        $photoRules = $photoEnabled
            ? ($photoRequired
                ? [$this->requiredIf($photoConfig), 'file', 'max:5120']
                : ['nullable', 'file', 'max:5120'])
            : [];

        // Property names in the error bag match the Livewire component
        // property names (camelCase) so `assertHasErrors(['companyName'])`
        // lights up the right input. The component maps them to DB
        // columns in submit().
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'address' => ['required', 'string', 'max:255'],
            'testimonial' => ['required', 'string', 'max:2000', new ZeroWidthFreeText()],
            'consentGiven' => ['sometimes', 'boolean'],
            'rating' => [$space->rating_enabled ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'companyName' => $companyRules,
            'socialUrl' => $socialRules,
            'profilePhoto' => $photoRules,
            'website' => ['nullable', new Honeypot()],
        ];

        return Validator::make($input, $rules);
    }

    /**
     * Build a rule list containing the field_config "required" check.
     * If the field is disabled OR enabled-but-not-required, this returns
     * an empty array (no rules), so the field is not validated at all.
     */
    private function requiredIf(array $config): \App\Rules\RequiredWhenFieldConfig
    {
        return new RequiredWhenFieldConfig($config);
    }

    private function ipKey(Request $request, Space $space): string
    {
        return 'submit|ip|'.$request->ip().'|space|'.$space->id;
    }

    private function emailKey(string $email, Request $request, Space $space): string
    {
        return 'submit|email|'.$email.'|space|'.$space->id;
    }

    private function pickThanks(): string
    {
        $list = (array) config('messages.submission_thanks', []);

        return $list === []
            ? 'Thanks for your testimonial!'
            : (string) $list[array_rand($list)];
    }
}
