<?php

namespace App\Http\Requests;

use App\Models\Space;
use App\Rules\Honeypot;
use App\Rules\RequiredWhenFieldConfig;
use App\Rules\ZeroWidthFreeText;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The single FormRequest for public /s/{slug} submissions.
 *
 * NOTE: This class is here for documentation/IDE support; the public
 * path actually runs through `SubmissionService::makeValidator()` so
 * the validation can be exercised in unit tests without booting a
 * controller. The rules below mirror the service's rule set exactly.
 * If you change one, change the other.
 */
class StoreTestimonialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public route
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Space $space */
        $space = $this->route('space');
        $fc = $space?->field_config ?? Space::defaultFieldConfig();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:180'],
            'address' => ['required', 'string', 'max:255'],
            'testimonial' => ['required', 'string', 'max:2000', new ZeroWidthFreeText()],
            'consent_given' => ['sometimes', 'boolean'],
            'rating' => [$space?->rating_enabled ? 'required' : 'nullable', 'integer', 'between:1,5'],
            'company_name' => ['nullable', 'string', 'max:160', new RequiredWhenFieldConfig($fc['company_name'] ?? [])],
            'social_url' => ['nullable', 'string', 'url', 'max:255', new RequiredWhenFieldConfig($fc['social_url'] ?? [])],
            'profile_photo' => ['nullable', 'file', 'max:5120', new RequiredWhenFieldConfig($fc['profile_photo'] ?? [])],
            'website' => ['nullable', new Honeypot()],
        ];
    }
}
