{{--
    Wave 0b — /privacy/request-deletion view (G2).
    The layout is now cardless + max-w-2xl (set on the component's
    #[Layout] attribute), so this view draws its own single card.
    Behavior is unchanged: every data-testid, every wire:model property
    name (email, spaceSlug, testimonialId, website_url honeypot), all
    validation messages, the loading pattern, and the identical
    thank-you body across all scenarios are preserved.
--}}
<div class="space-y-4 sm:space-y-6"
     data-testid="deletion-form-page">

    @if ($submitted)
        {{-- Same thank-you for every outcome (valid, unknown
             slug, soft-deleted, foreign testimonial, honeypot,
             throttle). The body is intentionally identical
             across all five "nothing-revealed" cases; the
             NOTHING-REVEALED test asserts this byte-for-byte. --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-indigo-600/10 p-6 sm:p-8 text-center space-y-3"
             data-testid="deletion-thanks">
            <span class="gst-pop inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                  data-testid="deletion-thanks-icon"
                  aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900" data-testid="deletion-thanks-title">
                Thanks — your request has been recorded.
            </h1>
            <p class="text-sm text-gray-600" data-testid="deletion-thanks-body">
                We received your request. If the details match a testimonial in this Space, the Space owner will see it on their dashboard and can remove it.
            </p>
        </div>
    @else
        {{-- Title on the canvas (not a second card) --}}
        <header class="space-y-1">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900" data-testid="deletion-title">Request removal of a testimonial</h1>
            <p class="text-sm text-gray-600">Use this form to ask the owner to remove a testimonial you submitted.</p>
        </header>

        <form wire:submit="submit"
              class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-indigo-600/10 p-4 sm:p-6 space-y-4"
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
                <p class="mt-1 text-xs text-gray-600">The slug appears in the public URL <code>/s/&lt;slug&gt;</code>.</p>
                @error('spaceSlug') <p class="mt-1 text-sm text-red-600" data-testid="deletion-slug-error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="deletion-testimonial-id" class="block text-sm font-medium text-gray-700">Testimonial id (optional)</label>
                <input id="deletion-testimonial-id" type="text" wire:model="testimonialId" maxlength="20"
                       class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                       data-testid="deletion-testimonial-id" />
                <p class="mt-1 text-xs text-gray-600">Only set this if you have a record id. We accept it only when it matches a testimonial in the Space above.</p>
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
                <x-primary-button class="gst-btn w-full sm:w-auto !rounded-xl !shadow-lg !shadow-indigo-600/20"
                                  wire:loading.attr="disabled"
                                  wire:target="submit"
                                  data-testid="deletion-submit">
                    <span wire:loading.remove wire:target="submit">Send request</span>
                    <span wire:loading wire:target="submit" data-testid="deletion-submit-loading">Working...</span>
                </x-primary-button>
            </div>
        </form>
    @endif
</div>
