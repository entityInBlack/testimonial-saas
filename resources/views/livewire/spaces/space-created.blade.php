<div>
    <x-slot name="header">
        <x-page-header
            :title="__('Space created')"
            data-testid="space-created-page-header"
        />
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8 shadow-xl shadow-indigo-600/10 space-y-4"
                 data-testid="success-page">

                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <p class="text-sm text-gray-700">
                        Your Space <strong>{{ $space->title }}</strong> is live at:
                    </p>
                </div>

                <div class="flex items-center gap-2 border border-gray-200 rounded-xl p-3 bg-gray-50"
                     data-testid="public-url-box">
                    <code id="public-url" class="flex-1 text-sm break-all font-mono" data-testid="public-url">{{ $publicUrl }}</code>
                    <button type="button"
                            x-data="{ copied: false }"
                            x-on:click="
                                navigator.clipboard.writeText(document.getElementById('public-url').textContent);
                                copied = true;
                                setTimeout(() => copied = false, 1500);
                            "
                            :class="copied ? 'bg-green-600' : 'bg-indigo-600'"
                            class="app-lift inline-flex items-center justify-center min-h-[44px] px-4 py-2 text-white text-sm font-semibold rounded-xl"
                            data-testid="copy-link">
                        <span x-text="copied ? 'Copied' : 'Copy Link'">Copy Link</span>
                    </button>
                </div>

                <p class="text-xs text-gray-600">
                    Share this link with customers so they can leave a testimonial.
                </p>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <a href="{{ route('spaces.index') }}" wire:navigate
                       class="app-lift inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-white border border-gray-300 rounded-xl text-sm font-semibold text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                       data-testid="go-to-dashboard">
                        Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
