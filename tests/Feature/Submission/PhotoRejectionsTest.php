<?php

use App\Exceptions\PhotoRejectedException;
use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Support\PhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Photo rejections. Each branch must throw PhotoRejectedException with
 * a generic message — no path, no filename, no internal detail.
 */

function photoEnabledSpace(): Space
{
    return Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => [
            'company_name' => ['enabled' => false, 'required' => false],
            'social_url' => ['enabled' => false, 'required' => false],
            'profile_photo' => ['enabled' => true, 'required' => false],
        ],
    ]);
}

test('wrong MIME (gif) is rejected', function () {
    Storage::fake('public');
    $photo = UploadedFile::fake()->image('any.gif', 800, 600);

    expect(fn () => app(PhotoProcessor::class)->handle($photo))
        ->toThrow(PhotoRejectedException::class, 'Unsupported photo format.');
});

test('a non-image file is rejected as invalid', function () {
    Storage::fake('public');
    // A plain text file with an image extension is NOT a real image.
    $file = UploadedFile::fake()->createWithContent('face.jpg', 'this is plain text, not an image');

    expect(fn () => app(PhotoProcessor::class)->handle($file))
        ->toThrow(PhotoRejectedException::class, 'Invalid photo.');
});

test('> 5 MB is rejected as too large', function () {
    Storage::fake('public');
    // 6 MB jpeg
    $photo = UploadedFile::fake()->image('huge.jpg', 3000, 2000)->size(6 * 1024);

    expect(fn () => app(PhotoProcessor::class)->handle($photo))
        ->toThrow(PhotoRejectedException::class, 'Photo is too large.');
});

test('> 4000 px on either side is rejected as too large in pixels', function () {
    Storage::fake('public');
    $photo = UploadedFile::fake()->image('wide.jpg', 4500, 1000);

    expect(fn () => app(PhotoProcessor::class)->handle($photo))
        ->toThrow(PhotoRejectedException::class, 'Photo is too large in pixels.');
});

test('the Livewire component surfaces a generic photo error to the user', function () {
    Storage::fake('public');
    $space = photoEnabledSpace();
    $photo = UploadedFile::fake()->image('face.gif', 800, 600);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, would recommend it.')
        ->set('profilePhoto', $photo)
        ->call('submit')
        ->assertSet('photoErrorMessage', 'Unsupported photo format.')
        ->assertSet('submitted', false);
});
