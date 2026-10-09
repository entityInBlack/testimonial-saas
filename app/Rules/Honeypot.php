<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Honeypot field. The validation always passes here — the controller
 * silently no-ops a filled honeypot and shows the thank-you page.
 *
 * Surfacing a validation error on the honeypot would reveal the trap to
 * the bot operator, which is the one thing we MUST NOT do. So the rule
 * returns true regardless of input, and the controller does the rest.
 */
class Honeypot implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // intentionally always passes; see class doc.
    }
}
