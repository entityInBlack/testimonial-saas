<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Normalizer;

/**
 * Body rule for the testimonial field. Applied AFTER `trim` and Laravel's
 * `string|max:2000` checks. Pipeline:
 *   1. Strip zero-width chars (U+200B, U+200C, U+200D, U+FEFF).
 *   2. If `intl` is loaded, normalise to FORM_C (PRD §6: "needs PHP
 *      intl and is skipped if intl is missing").
 *   3. Reject if the result is empty, whitespace-only, or contains no
 *      letters (so a string of only symbols / emoji / spaces fails).
 *
 * The check is intentionally conservative — the goal is to reject the
 * obvious junk-only bodies, not to police content.
 */
class ZeroWidthFreeText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('Invalid :attribute.');

            return;
        }

        // 1) strip zero-width
        $stripped = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $value);

        // 2) intl normalisation (optional)
        if (extension_loaded('intl') && class_exists(Normalizer::class)) {
            $stripped = Normalizer::normalize($stripped, Normalizer::FORM_C) ?: $stripped;
        }

        // 3) collapse whitespace, then check the body has at least one letter
        $collapsed = trim(preg_replace('/\s+/u', ' ', $stripped) ?? '');

        if ($collapsed === '') {
            $fail('The :attribute can\'t be empty.');

            return;
        }

        // No letters at all → symbol/emoji/zero-width-only. The pattern
        // matches anything that is NOT a Unicode letter.
        if (preg_match('/^\P{L}+$/u', $collapsed) === 1) {
            $fail('The :attribute must include at least one letter.');

            return;
        }
    }
}
