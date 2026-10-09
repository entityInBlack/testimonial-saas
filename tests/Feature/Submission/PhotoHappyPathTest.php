<?php

use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Support\PhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Photo happy path. Hard Rule 13:
 *   - saved under photos/ on the public disk
 *   - webp after re-encode
 *   - random name, no original filename in the path
 *   - photo_url = Storage::disk('public')->url($path)
 */

test('a valid photo is re-encoded to webp under photos/ and the URL is reachable', function () {
    Storage::fake('public');

    $space = Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => [
            'company_name' => ['enabled' => false, 'required' => false],
            'social_url' => ['enabled' => false, 'required' => false],
            'profile_photo' => ['enabled' => true, 'required' => false],
        ],
    ]);

    $photo = UploadedFile::fake()->image('face.jpg', 1500, 1000);

    Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, I would buy again.')
        ->set('profilePhoto', $photo)
        ->call('submit')
        ->assertHasNoErrors();

    $row = \Illuminate\Support\Facades\DB::table('testimonials')->first();
    expect($row->profile_photo)->not->toBeNull();

    // 1) path is under photos/
    expect($row->profile_photo)->toStartWith('photos/');
    expect($row->profile_photo)->toEndWith('.webp');

    // 2) random name — no original filename leak; the path is exactly
    //    `photos/{32chars}.webp` with no extra subdirs.
    expect($row->profile_photo)->not->toContain('face');
    expect($row->profile_photo)->not->toContain('jpg');
    expect($row->profile_photo)->not->toContain('\\');
    expect($row->profile_photo)->toMatch('#^photos/[a-z0-9]{32}\.webp$#');

    // 3) file actually exists on disk and the bytes are webp
    Storage::disk('public')->assertExists($row->profile_photo);
    $bytes = Storage::disk('public')->get($row->profile_photo);
    // webp files start with "RIFF....WEBP"
    expect(substr($bytes, 0, 4))->toBe('RIFF');
    expect(substr($bytes, 8, 4))->toBe('WEBP');

    // 4) url() returns the public URL
    $url = Storage::disk('public')->url($row->profile_photo);
    expect($url)->toContain('/storage/photos/');
});

test('PhotoProcessor returns a path of the form photos/{32chars}.webp', function () {
    Storage::fake('public');
    $photo = UploadedFile::fake()->image('any.jpg', 800, 600);

    $path = app(PhotoProcessor::class)->handle($photo);

    expect($path)->toMatch('#^photos/[a-z0-9]{32}\.webp$#');
    Storage::disk('public')->assertExists($path);
});
