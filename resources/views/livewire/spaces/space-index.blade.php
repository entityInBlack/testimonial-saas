<div>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Spaces') }}
            </h2>
            <div class="flex items-center gap-3">
                <a href="{{ route('spaces.deleted') }}" wire:navigate
                   class="text-sm text-gray-600 hover:text-gray-900"
                   data-testid="deleted-tab-link">
                    Deleted Spaces
                </a>
                @if (! $this->isAtCap)
                    <a href="{{ route('spaces.new') }}" wire:navigate
                       class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700"
                       data-testid="new-space-link">
                        New Space
                    </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-green-50 border border-green-200 text-green-900 px-4 py-3 rounded"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @if ($this->showWarningBanner)
                <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded"
                     data-testid="cap-warning">
                    <p class="text-sm">
                        You're using {{ $this->liveSpaceCount }} of {{ $this->maxSpaces }} Spaces
                        (the Free plan limit).
                    </p>
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg divide-y divide-gray-100"
                 data-testid="space-list">
                @forelse ($this->spaces as $space)
                    <div class="flex items-center justify-between p-4" data-testid="space-row">
                        <div>
                            <div class="font-medium text-gray-900">{{ $space->title }}</div>
                            <div class="text-xs text-gray-500">
                                /s/{{ $space->slug }} · public_id: {{ $space->public_id }}
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('spaces.embed', ['space' => $space->id]) }}" wire:navigate
                               class="text-sm text-indigo-600 hover:text-indigo-900"
                               data-testid="embed-link">
                                Embed
                            </a>
                            <a href="{{ route('spaces.edit', ['space' => $space->id]) }}" wire:navigate
                               class="text-sm text-indigo-600 hover:text-indigo-900"
                               data-testid="edit-link">
                                Edit
                            </a>
                            <button wire:click="delete({{ $space->id }})"
                                    wire:confirm="Delete '{{ $space->title }}'? You can restore it from the Deleted tab within {{ (int) config('purge.retention_days', 30) }} days."
                                    class="text-sm text-red-600 hover:text-red-900"
                                    data-testid="delete-button">
                                Delete
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-500" data-testid="empty-state">
                        You don't have any Spaces yet.
                        <a href="{{ route('spaces.new') }}" wire:navigate class="underline text-indigo-600">
                            Create your first Space
                        </a>.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
