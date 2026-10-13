{{--
    Wave 0b — /s/{slug} public submission view (G1).
    The layout is now cardless + max-w-2xl (set on the component's
    #[Layout] attribute), so this view draws its own single card.
    Behavior is unchanged: every data-testid, every wire:model property
    name (including the honeypot "website" field), all validation
    messages and all PHP above the closing "?>" stay as they are.
--}}
<div class="space-y-4 sm:space-y-6"
     data-testid="public-submission">

    @if ($submitted)
        {{-- Thank-you / locked / honeypot / duplicate all share this view --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-indigo-600/10 p-6 sm:p-8 text-center space-y-3"
             data-testid="thanks-page">
            {{-- Check-circle icon chip — outside the h1, placed BEFORE it. --}}
            <span class="gst-pop inline-flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600"
                  data-testid="thanks-icon"
                  aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>

            <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-gray-900">
                {{ $thanksMessage }}
            </h1>
            <p class="text-sm text-gray-600">
                Thanks for taking the time to share.
            </p>
        </div>

        <div class="text-center">
            <a href="{{ route('landing') }}"
               class="inline-flex items-center min-h-[44px] px-2 text-sm text-indigo-700 hover:text-indigo-900 underline underline-offset-2"
               data-testid="back-home-link">
                Back to home
            </a>
        </div>
    @else
        {{-- Space header (sits on the canvas, not in a card) --}}
        <header class="space-y-1" data-testid="space-header">
            <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900">
                {{ $space->title }}
            </h1>
            @if ($space->subtitle)
                <p class="text-sm text-gray-600">{{ $space->subtitle }}</p>
            @endif
            @if ($space->ask)
                <p class="text-sm text-gray-800 mt-2">{{ $space->ask }}</p>
            @endif
        </header>

        @if ($locked)
            <div class="bg-amber-50 border border-amber-200 text-amber-900 px-4 py-3 rounded-xl"
                 data-testid="locked-message">
                <p class="text-sm">
                    This Space is not accepting responses right now.
                </p>
            </div>

            <div class="text-center">
                <a href="{{ route('landing') }}"
                   class="inline-flex items-center min-h-[44px] px-2 text-sm text-indigo-700 hover:text-indigo-900 underline underline-offset-2"
                   data-testid="back-home-link">
                    Back to home
                </a>
            </div>
        @else
            @if ($throttledMessage)
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded-xl"
                     data-testid="throttle-error">
                    <p class="text-sm">{{ $throttledMessage }}</p>
                </div>
            @endif

            @if ($photoErrorMessage)
                <div class="bg-red-50 border border-red-200 text-red-900 px-4 py-3 rounded-xl"
                     data-testid="photo-error">
                    <p class="text-sm">{{ $photoErrorMessage }}</p>
                </div>
            @endif

            <form wire:submit="submit"
                  enctype="multipart/form-data"
                  class="rounded-2xl border border-gray-200 bg-white shadow-xl shadow-indigo-600/10 p-4 sm:p-6 space-y-4"
                  data-testid="submission-form">
                @csrf

                {{-- Locked fields: name / email / address --}}
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" type="text" wire:model="name" maxlength="120" required
                           class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                           data-testid="name-input" />
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input id="email" type="email" wire:model="email" maxlength="180" required
                           class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                           data-testid="email-input" />
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input id="address" type="text" wire:model="address" maxlength="255" required
                           class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                           data-testid="address-input" />
                    @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="testimonial" class="block text-sm font-medium text-gray-700">Your testimonial</label>
                    <textarea id="testimonial" wire:model="testimonial" maxlength="2000" required rows="4"
                              class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                              data-testid="testimonial-input"></textarea>
                    @error('testimonial') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Optional fields driven by field_config --}}
                @php
                    $fc = $space->field_config ?? \App\Models\Space::defaultFieldConfig();
                @endphp

                @if (($fc['company_name']['enabled'] ?? false))
                    <div>
                        <label for="companyName" class="block text-sm font-medium text-gray-700">
                            Company name @if (($fc['company_name']['required'] ?? false))<span class="text-red-600">*</span>@endif
                        </label>
                        <input id="companyName" type="text" wire:model="companyName" maxlength="160"
                               @if (($fc['company_name']['required'] ?? false)) required @endif
                               class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="company-name-input" />
                        @error('companyName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if (($fc['social_url']['enabled'] ?? false))
                    <div>
                        <label for="socialUrl" class="block text-sm font-medium text-gray-700">
                            Social URL @if (($fc['social_url']['required'] ?? false))<span class="text-red-600">*</span>@endif
                        </label>
                        <input id="socialUrl" type="url" wire:model="socialUrl" maxlength="255"
                               @if (($fc['social_url']['required'] ?? false)) required @endif
                               class="mt-1 block w-full text-base rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="social-url-input" />
                        @error('socialUrl') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if (($fc['profile_photo']['enabled'] ?? false))
                    <div>
                        <label for="profilePhoto" class="block text-sm font-medium text-gray-700">
                            Profile photo @if (($fc['profile_photo']['required'] ?? false))<span class="text-red-600">*</span>@endif
                        </label>
                        <input id="profilePhoto" type="file" wire:model="profilePhoto"
                               accept="image/jpeg,image/png,image/webp"
                               @if (($fc['profile_photo']['required'] ?? false)) required @endif
                               class="mt-1 block w-full text-sm rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 min-h-[44px]"
                               data-testid="profile-photo-input" />
                        @error('profilePhoto') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Rating (only when enabled) — five buttons in one row at 320 px --}}
                @if ($space->rating_enabled)
                    <div>
                        <span class="block text-sm font-medium text-gray-700">Rating</span>
                        <div class="mt-2 flex gap-2" role="radiogroup" aria-label="Rating">
                            @for ($i = 1; $i <= 5; $i++)
                                <button type="button"
                                        wire:click="$set('rating', {{ $i }})"
                                        :class="$wire.rating >= {{ $i }} ? 'bg-amber-400 text-white border-amber-400' : 'bg-white text-gray-700 border-gray-300'"
                                        class="flex-1 min-w-[40px] inline-flex items-center justify-center min-h-[44px] rounded-md border px-1 text-sm sm:text-base font-semibold"
                                        aria-label="Rate {{ $i }} out of 5"
                                        data-testid="rating-{{ $i }}">
                                    <span aria-hidden="true">{{ $i }}</span>
                                </button>
                            @endfor
                        </div>
                        @error('rating') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                {{-- Consent — normal-size checkbox, 44 px tap area on the label --}}
                <div>
                    <label class="flex items-start gap-2 min-h-[44px] py-2 text-sm text-gray-700 cursor-pointer">
                        <input type="checkbox" wire:model="consentGiven"
                               class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                               data-testid="consent-input" />
                        <span>
                            {{ config('consent.texts.'.config('consent.current')) }}
                        </span>
                    </label>
                </div>

                {{-- Honeypot (visually hidden, never tabbable) --}}
                <div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;">
                    <label for="website">Website</label>
                    <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off" />
                </div>

                <div>
                    <x-primary-button class="gst-btn w-full sm:w-auto !rounded-xl !shadow-lg !shadow-indigo-600/20"
                                      data-testid="submit-button">
                        Submit testimonial
                    </x-primary-button>
                </div>

                <div class="text-center">
                    <a href="{{ route('landing') }}"
                       class="inline-flex items-center min-h-[44px] px-2 text-sm text-indigo-700 hover:text-indigo-900 underline underline-offset-2"
                       data-testid="back-home-link">
                        Back to home
                    </a>
                </div>
            </form>
        @endif
    @endif
</div>
