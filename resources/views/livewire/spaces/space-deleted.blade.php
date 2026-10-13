<div>
    <x-slot name="header">
        <x-page-header :title="__('Deleted Spaces')" data-testid="space-deleted-page-header">
            <a href="{{ route('spaces.index') }}" wire:navigate
               class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 rounded-xl bg-white border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
               data-testid="back-to-spaces">
                Back to Spaces
            </a>
        </x-page-header>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="rounded-xl bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm"
                     data-testid="status-flash">
                    {{ session('status') }}
                </div>
            @endif

            @error('restore')
                <div class="rounded-xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm"
                     data-testid="restore-error">
                    <p>{{ $message }}</p>
                </div>
            @enderror

            <p class="text-sm text-gray-600">
                Spaces deleted in the last {{ $this->retentionDays }} days can be restored.
                Older Spaces are kept as tombstones to claim the slug forever — they cannot
                be restored.
            </p>

            <div class="rounded-2xl border border-gray-200 bg-white shadow-sm"
                 data-testid="deleted-list">
                <x-spaces.deleted-list
                    :spaces="$this->spaces"
                    action="restore"
                />
            </div>
        </div>
    </div>
</div>
