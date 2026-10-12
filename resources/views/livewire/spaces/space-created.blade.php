<div>
    <x-slot name="header">
        <x-page-header
            :title="__('Space created')"
            data-testid="space-created-page-header"
        />
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="bg-white shadow-sm rounded-lg p-6 space-y-4"
                 data-testid="success-page">

                <p class="text-sm text-gray-700">
                    Your Space <strong>{{ $space->title }}</strong> is live at:
                </p>

                <div class="flex items-center gap-2 border border-gray-200 rounded-md p-3 bg-gray-50"
                     data-testid="public-url-box">
                    <code id="public-url" class="flex-1 text-sm break-all" data-testid="public-url">{{ $publicUrl }}</code>
                    <button type="button"
                            x-data="{ copied: false }"
                            x-on:click="
                                navigator.clipboard.writeText(document.getElementById('public-url').textContent);
                                copied = true;
                                setTimeout(() => copied = false, 1500);
                            "
                            :class="copied ? 'bg-green-600' : 'bg-indigo-600'"
                            class="inline-flex items-center px-3 py-1.5 text-white text-xs font-semibold rounded"
                            data-testid="copy-link">
                        <span x-text="copied ? 'Copied' : 'Copy Link'">Copy Link</span>
                    </button>
                </div>

                <p class="text-xs text-gray-500">
                    Share this link with customers so they can leave a testimonial.
                </p>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <a href="{{ route('spaces.index') }}" wire:navigate
                       class="inline-flex items-center justify-center min-h-[44px] sm:min-h-0 px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-sm text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                       data-testid="go-to-dashboard">
                        Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
