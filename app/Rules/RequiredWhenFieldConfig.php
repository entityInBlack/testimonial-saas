<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "Required when field_config says so" rule for the three optional
 * fields on the public submission form. The closure receives the Space
 * via the rule's constructor; the field_config JSON shape is
 * `{key: {enabled, required}}` (data-model §3.3).
 *
 * Behaviour:
 *   - If the field is disabled in field_config → no requirement.
 *   - If the field is enabled but not required → no requirement.
 *   - If the field is enabled and required → value must be present
 *     and non-empty.
 */
class RequiredWhenFieldConfig implements ValidationRule
{
    public function __construct(
        /** @var array{enabled?: bool, required?: bool} */
        private array $config
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $enabled = (bool) ($this->config['enabled'] ?? false);
        $required = (bool) ($this->config['required'] ?? false);

        if (! ($enabled && $required)) {
            return;
        }

        if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
            $fail('The :attribute is required.');
        }
    }
}
