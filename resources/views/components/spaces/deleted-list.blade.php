{{--
    Shared deleted-Spaces list used by:
      - resources/views/livewire/spaces/space-deleted.blade.php
        (the /spaces/deleted page, wire:click="restore")
      - resources/views/livewire/dashboard/dashboard-index.blade.php
        (the dashboard "Deleted Spaces" tab, wire:click="restoreDeletedSpace")

    Props:
      - $spaces:   Eloquent collection of soft-deleted Space models
                   (already filtered to the retention window — the
                   component does not do any further filtering).
      - $action:   The Livewire method name to call when the
                   Restore button is clicked, e.g. 'restore' or
                   'restoreDeletedSpace'. Rendered as
                   `wire:click="$action($space->id)"`.

    Both call sites render `data-testid="deleted-row"`, `restore-button`,
    and an empty-state div. The wrapping card differs per call site
    (status / error / retention copy styling) so the wrapper markup
    stays at the call site — this component is the SHARED list body
    only.
--}}
@props([
    'spaces' => collect(),
    'action' => 'restore',
])
<div class="divide-y divide-gray-100">
    @forelse ($spaces as $space)
        <div class="flex items-center justify-between p-4"
             data-testid="deleted-row">
            <div>
                <div class="font-medium text-gray-900">{{ $space->title }}</div>
                <div class="text-xs text-gray-500">
                    /s/{{ $space->slug }} ·
                    deleted {{ $space->deleted_at?->diffForHumans() }}
                </div>
            </div>
            <button wire:click="{{ $action }}({{ $space->id }})"
                    class="text-sm text-indigo-600 hover:text-indigo-900"
                    data-testid="restore-button">
                Restore
            </button>
        </div>
    @empty
        <div class="p-6 text-center text-gray-500"
             data-testid="deleted-empty-state">
            No deleted Spaces.
        </div>
    @endforelse
</div>
