<?php

use App\Exceptions\PhotoRejectedException;
use App\Livewire\PublicSubmission;
use App\Models\Space;
use App\Models\Testimonial;
use App\Support\PhotoProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * Photo rollback. If the DB insert fails after the file was written,
 * the file MUST be removed from the public disk (Hard Rule 13: no
 * orphan files; only the purge job may remove files).
 */

test('if the DB insert fails after the photo is saved, the file is rolled back', function () {
    Storage::fake('public');
    $space = Space::factory()->create([
        'rating_enabled' => false,
        'field_config' => [
            'company_name' => ['enabled' => false, 'required' => false],
            'social_url' => ['enabled' => false, 'required' => false],
            'profile_photo' => ['enabled' => true, 'required' => false],
        ],
    ]);

    // Force the next Testimonial::create to throw. Hard Rule 13 says the
    // photo file MUST be deleted from the public disk in that case
    // (no orphan files — only the purge job may remove files).
    Testimonial::creating(function () {
        throw new \RuntimeException('simulated DB failure');
    });

    $photo = UploadedFile::fake()->image('face.jpg', 1200, 800);

    expect(fn () => Livewire::test(PublicSubmission::class, ['slug' => $space->slug])
        ->set('name', 'A')
        ->set('email', 'a@example.test')
        ->set('address', '1 St')
        ->set('testimonial', 'great product, would buy again.')
        ->set('profilePhoto', $photo)
        ->call('submit')
    )->toThrow(\RuntimeException::class, 'simulated DB failure');

    // The public disk must be empty under photos/ — no orphans.
    $files = Storage::disk('public')->allFiles('photos');
    expect($files)->toBe([]);
});
