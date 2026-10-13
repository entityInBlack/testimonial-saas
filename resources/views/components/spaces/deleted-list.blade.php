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
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 sm:p-5 min-w-0"
             data-testid="deleted-row">
            <div class="min-w-0">
                <div class="font-semibold text-gray-900">{{ $space->title }}</div>
                <div class="mt-1 text-sm text-gray-600">
                    /s/{{ $space->slug }} ·
                    deleted {{ $space->deleted_at?->diffForHumans() }}
                </div>
            </div>
            <button wire:click="{{ $action }}({{ $space->id }})"
                    class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-indigo-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    data-testid="restore-button">
                Restore
            </button>
        </div>
    @empty
        <div class="p-6 text-center text-sm text-gray-600"
             data-testid="deleted-empty-state">
            No deleted Spaces.
        </div>
    @endforelse
</div>
