<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use Illuminate\Support\Facades\DB;

/*
 * field_config drives the optional fields. Required=true means the
 * field must be present; enabled=false means the field isn't rendered
 * and isn't required.
 */

function customFieldConfig(array $overrides): array
{
    return array_merge([
        'company_name' => ['enabled' => true,  'required' => false],
        'social_url'   => ['enabled' => false, 'required' => false],
        'profile_photo' => ['enabled' => false, 'required' => false],
    ], $overrides);
}

test('company_name is required when field_config says so', function () {
    $space = Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => customFieldConfig([
            'company_name' => ['enabled' => true, 'required' => true],
        ]),
    ]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, I would recommend it.')
        ->set('companyName', null) // missing
        ->call('submit')
        ->assertHasErrors(['companyName']);

    expect(DB::table('testimonials')->where('space_id', $space->id)->count())->toBe(0);
});

test('company_name is NOT required when field_config.required=false', function () {
    $space = Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => customFieldConfig([
            'company_name' => ['enabled' => true, 'required' => false],
        ]),
    ]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, would recommend it.')
        ->call('submit')
        ->assertHasNoErrors();

    expect(DB::table('testimonials')->where('space_id', $space->id)->count())->toBe(1);
});

test('a disabled field is not validated and not required', function () {
    $space = Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => customFieldConfig([
            'social_url' => ['enabled' => false, 'required' => false],
        ]),
    ]);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, would recommend it.')
        ->set('socialUrl', 'not-a-url') // invalid + field disabled
        ->call('submit')
        ->assertHasNoErrors();

    expect(DB::table('testimonials')->where('space_id', $space->id)->count())->toBe(1);
});
