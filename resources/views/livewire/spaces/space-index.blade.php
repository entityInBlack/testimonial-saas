<div>
    <x-slot name="header">
        <x-page-header :title="__('Spaces')" data-testid="spaces-page-header">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('spaces.deleted') }}" wire:navigate
                   class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                   data-testid="deleted-tab-link">
                    Deleted Spaces
                </a>
                @if (! $this->isAtCap)
                    <a href="{{ route('spaces.new') }}" wire:navigate
                       class="app-lift inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl bg-indigo-600 border border-transparent text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @if ($this->showWarningBanner)
                <div class="rounded-xl bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm"
                     data-testid="cap-warning">
                    <p>
                        You're using {{ $this->liveSpaceCount }} of {{ $this->maxSpaces }} Spaces
                        (the Free plan limit).
                    </p>
                </div>
            @endif

            <div class="space-y-4"
                 data-testid="space-list">
                @forelse ($this->spaces as $space)
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-4 sm:p-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0"
                         data-testid="space-row">
                        <div class="min-w-0 space-y-1">
                            <div class="font-semibold text-gray-900">
                                <a href="{{ route('inbox.index', ['spaceId' => $space->id]) }}"
                                   wire:navigate
                                   class="text-gray-900 hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded"
                                   data-testid="space-row-title-link">
                                    {{ $space->title }}
                                </a>
                            </div>
                            <div class="text-sm text-gray-600">
                                <a href="{{ url('/s/'.$space->slug) }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="text-indigo-700 underline hover:text-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 rounded break-all"
                                   data-testid="space-row-public-link">
                                    /s/{{ $space->slug }}
                                </a>
                            </div>
                            <div class="text-xs text-gray-600">
                                <span class="inline-flex items-center rounded-md bg-gray-50 border border-gray-200 px-2 py-0.5 font-mono">
                                    public_id: {{ $space->public_id }}
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <a href="{{ route('spaces.embed', ['space' => $space->id]) }}" wire:navigate
                               class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                               data-testid="embed-link">
                                Embed
                            </a>
                            <a href="{{ route('spaces.edit', ['space' => $space->id]) }}" wire:navigate
                               class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                               data-testid="edit-link">
                                Edit
                            </a>
                            <button wire:click="delete({{ $space->id }})"
                                    wire:confirm="Delete '{{ $space->title }}'? You can restore it from the Deleted tab within {{ (int) config('purge.retention_days', 30) }} days."
                                    class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-red-200 text-sm font-semibold text-red-700 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                    data-testid="delete-button">
                                Delete
                            </button>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm p-8 text-center"
                         data-testid="empty-state">
                        <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5v10.5H3.75z M3.75 10.5h16.5" />
                            </svg>
                        </div>
                        <p class="text-gray-700">You don't have any Spaces yet.</p>
                        <a href="{{ route('spaces.new') }}" wire:navigate
                           class="app-lift inline-flex items-center justify-center min-h-[44px] mt-4 px-6 py-3 rounded-xl bg-indigo-600 text-white text-sm font-semibold shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                           data-testid="empty-create-space-link">
                            Create your first Space
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
