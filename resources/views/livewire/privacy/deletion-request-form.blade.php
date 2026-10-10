<div>
    <div class="py-8 px-4 sm:px-6">
        <div class="mx-auto w-full max-w-2xl space-y-4"
             data-testid="deletion-form-page">

            @if ($submitted)
                {{-- Same thank-you for every outcome (valid, unknown
                     slug, soft-deleted, foreign testimonial, honeypot,
                     throttle). The body is intentionally identical
                     across all five "nothing-revealed" cases; the
                     NOTHING-REVEALED test asserts this byte-for-byte. --}}
                <div class="bg-white shadow-sm rounded-lg p-6 text-center space-y-3"
                     data-testid="deletion-thanks">
                    <h1 class="text-xl font-semibold text-gray-900" data-testid="deletion-thanks-title">
                        Thanks — your request has been recorded.
                    </h1>
                    <p class="text-sm text-gray-600" data-testid="deletion-thanks-body">
                        We received your request. If the details match a testimonial in this Space, the Space owner will see it on their dashboard and can remove it.
                    </p>
                </div>
            @else
                <header class="bg-white shadow-sm rounded-lg p-6 space-y-2">
                    <h1 class="text-2xl font-semibold text-gray-900" data-testid="deletion-title">Request removal of a testimonial</h1>
                    <p class="text-sm text-gray-600">Use this form to ask the owner to remove a testimonial you submitted.</p>
                </header>

                <form wire:submit="submit"
                      class="bg-white shadow-sm rounded-lg p-6 space-y-4"
                      data-testid="deletion-form">
                    @csrf

                    <div>
                        <label for="deletion-email" class="block text-sm font-medium text-gray-700">Your email</label>
                        <input id="deletion-email" type="email" wire:model="email" maxlength="180" required
                               class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="deletion-email" />
                        @error('email') <p class="mt-1 text-sm text-red-600" data-testid="deletion-email-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="deletion-slug" class="block text-sm font-medium text-gray-700">Space slug</label>
                        <input id="deletion-slug" type="text" wire:model="spaceSlug" maxlength="60" required
                               class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="deletion-slug" />
                        <p class="mt-1 text-xs text-gray-500">The slug appears in the public URL <code>/s/&lt;slug&gt;</code>.</p>
                        @error('spaceSlug') <p class="mt-1 text-sm text-red-600" data-testid="deletion-slug-error">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="deletion-testimonial-id" class="block text-sm font-medium text-gray-700">Testimonial id (optional)</label>
                        <input id="deletion-testimonial-id" type="text" wire:model="testimonialId" maxlength="20"
                               class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="deletion-testimonial-id" />
                        <p class="mt-1 text-xs text-gray-500">Only set this if you have a record id. We accept it only when it matches a testimonial in the Space above.</p>
                        @error('testimonialId') <p class="mt-1 text-sm text-red-600" data-testid="deletion-testimonial-id-error">{{ $message }}</p> @enderror
                    </div>

                    {{-- Honeypot: visually hidden, never tabbable.
                         Name is "website_url" (picked by the Livewire
                         component and reported in the final report). --}}
                    <div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;">
                        <label for="deletion-website">Website</label>
                        <input id="deletion-website" type="text" wire:model="website_url" tabindex="-1" autocomplete="off" />
                    </div>

                    <div>
                        <button type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="inline-flex items-center justify-center px-5 py-2.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-sm text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 min-h-[44px]"
                                data-testid="deletion-submit">
                            <span wire:loading.remove wire:target="submit">Send request</span>
                            <span wire:loading wire:target="submit" data-testid="deletion-submit-loading">Working...</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
