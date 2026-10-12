<div>
    <x-slot name="header">
        <x-page-header :title="__('Spaces')" data-testid="spaces-page-header">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('spaces.deleted') }}" wire:navigate
                   class="text-sm text-gray-600 hover:text-gray-900"
                   data-testid="deleted-tab-link">
                    Deleted Spaces
                </a>
                @if (! $this->isAtCap)
                    <a href="{{ route('spaces.new') }}" wire:navigate
                       class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white hover:bg-indigo-700 active:bg-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                       data-testid="new-space-link">
                        New Space
                    </a>
                @endif
            </div>
        </x-page-header>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

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
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
                         data-testid="space-row">
                        <div class="min-w-0 space-y-1">
                            <div class="font-medium text-gray-900">
                                <a href="{{ route('inbox.index', ['spaceId' => $space->id]) }}"
                                   wire:navigate
                                   class="text-gray-900 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                                   data-testid="space-row-title-link">
                                    {{ $space->title }}
                                </a>
                            </div>
                            <div class="text-xs text-gray-500">
                                <a href="{{ url('/s/'.$space->slug) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="text-gray-500 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded break-all"
                                   data-testid="space-row-public-link">
                                    /s/{{ $space->slug }}
                                </a>
                            </div>
                            <div class="text-xs text-gray-500">
                                public_id: {{ $space->public_id }}
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-3">
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
