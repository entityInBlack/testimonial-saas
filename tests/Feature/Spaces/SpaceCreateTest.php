<?php

use App\Livewire\Spaces\SpaceForm;
use App\Livewire\Spaces\SpaceIndex;
use App\Models\Space;
use App\Models\User;
use Livewire\Livewire;

test('authenticated user can create a Space from the Livewire form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('spaces.new'))
        ->assertOk()
        ->assertSeeLivewire(SpaceForm::class);

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', '')
        ->set('slug', 'acme-wall')
        ->set('title', 'Acme Wall')
        ->set('subtitle', 'See what people say')
        ->set('ask', 'Tell us about Acme')
        ->set('ratingEnabled', true)
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect();

    $space = Space::where('slug', 'acme-wall')->first();

    expect($space)->not->toBeNull();
    expect($space->user_id)->toBe($user->id);
    expect($space->title)->toBe('Acme Wall');
    expect($space->name)->toBe('Acme Wall'); // name defaults to title
    expect($space->subtitle)->toBe('See what people say');
    expect($space->ask)->toBe('Tell us about Acme');
    expect($space->theme)->toBe('minimal_light');
    expect($space->rating_enabled)->toBeTrue();
});

test('name field defaults to the title when left blank', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', '')
        ->set('slug', 'untitled-space')
        ->set('title', 'My Untitled Space')
        ->set('ask', 'Your story')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::where('slug', 'untitled-space')->first();

    expect($space->name)->toBe('My Untitled Space');
});

test('public_id is generated on insert and is exactly 12 chars', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'Title')
        ->set('slug', 'public-id-test')
        ->set('title', 'Title')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::where('slug', 'public-id-test')->first();

    expect($space->public_id)->toBeString();
    expect(strlen($space->public_id))->toBe(12);
    expect($space->public_id)->toMatch('/^[a-z0-9]{12}$/');
});

test('public_id is unique across Spaces', function () {
    $user = User::factory()->create();

    $a = Space::factory()->for($user)->create();
    $b = Space::factory()->for($user)->create();

    expect($a->public_id)->not->toBe($b->public_id);
});

test('public_id cannot be changed after a Space is created (immutable)', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();
    $original = $space->public_id;

    // Direct Eloquent update must throw
    expect(fn () => $space->update(['public_id' => 'overridden00']))
        ->toThrow(LogicException::class, 'public_id is immutable');

    // Direct attribute set must also throw
    $space->refresh();
    expect(fn () => $space->public_id = 'overridden00')
        ->toThrow(LogicException::class, 'public_id is immutable');

    expect($space->fresh()->public_id)->toBe($original);
});

test('default field_config is applied on create when none is provided', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(SpaceForm::class)
        ->set('name', 'x')
        ->set('slug', 'default-fc')
        ->set('title', 'Default FC')
        ->set('ask', 'ask')
        ->set('theme', 'minimal_light')
        ->call('save')
        ->assertHasNoErrors();

    $space = Space::where('slug', 'default-fc')->first();

    expect($space->field_config)->toMatchArray([
        'company_name' => ['enabled' => true, 'required' => false],
        'social_url' => ['enabled' => false, 'required' => false],
        'profile_photo' => ['enabled' => false, 'required' => false],
    ]);
});

test('create form is unreachable for guests', function () {
    $this->get(route('spaces.new'))
        ->assertRedirect(route('login'));
});

test('index page lists only the owners LIVE Spaces', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    Space::factory()->for($alice)->count(2)->create();
    Space::factory()->for($bob)->count(3)->create();
    // soft-deleted for alice — should NOT show on /spaces
    Space::factory()->for($alice)->create()->delete();

    Livewire::actingAs($alice)
        ->test(SpaceIndex::class)
        ->assertSet('liveSpaceCount', 2)
        ->assertSeeLivewire(SpaceIndex::class);
});
