<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Deleted Spaces') }}
            </h2>
            <a href="{{ route('spaces.index') }}" wire:navigate
               class="text-sm text-gray-600 hover:text-gray-900"
               data-testid="back-to-spaces">
                Back to Spaces
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @error('restore')
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded"
                     data-testid="restore-error">
                    <p class="text-sm">{{ $message }}</p>
                </div>
            @enderror

            <p class="text-sm text-gray-600">
                Spaces deleted in the last {{ $this->retentionDays }} days can be restored.
                Older Spaces are kept as tombstones to claim the slug forever — they cannot
                be restored.
            </p>

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100"
                 data-testid="deleted-list">
                @forelse ($this->spaces as $space)
                    <div class="flex items-center justify-between p-4" data-testid="deleted-row">
                        <div>
                            <div class="font-medium text-gray-900">{{ $space->title }}</div>
                            <div class="text-xs text-gray-500">
                                /s/{{ $space->slug }} ·
                                deleted {{ $space->deleted_at?->diffForHumans() }}
                            </div>
                        </div>
                        <button wire:click="restore({{ $space->id }})"
                                class="text-sm text-indigo-600 hover:text-indigo-900"
                                data-testid="restore-button">
                            Restore
                        </button>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500" data-testid="empty-state">
                        No deleted Spaces.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
