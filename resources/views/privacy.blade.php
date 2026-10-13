<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Privacy — {{ config('app.name', 'Testimonial SaaS') }}</title>

        {{-- Wave 0a — D1: no remote fonts. The Tailwind sans family is
             the system stack (see tailwind.config.js). --}}

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* === Privacy page entrance + reduced-motion fail-safe ===
               All classes are prefixed `gst-` so they cannot collide
               with landing's `lvl-` classes. Mirrors the guest layout
               (Part 1b). The animated children rest in their NATURAL
               state (no opacity, no transform on the base rule);
               only the animation describes the way IN, with
               `backwards` fill so the hidden/offset first frame
               applies during each child's animation-delay. The
               resting computed state must be `transform: none` and
               `opacity: 1`. ============== */
            .gst-hero-fade > * {
                animation: gst-hero-in 0.55s ease-out backwards;
            }
            .gst-hero-fade > *:nth-child(1) { animation-delay: 0.00s; }
            .gst-hero-fade > *:nth-child(2) { animation-delay: 0.08s; }
            .gst-hero-fade > *:nth-child(3) { animation-delay: 0.16s; }
            .gst-hero-fade > *:nth-child(4) { animation-delay: 0.24s; }
            .gst-hero-fade > *:nth-child(5) { animation-delay: 0.32s; }
            .gst-hero-fade > *:nth-child(6) { animation-delay: 0.40s; }
            @keyframes gst-hero-in {
                from { opacity: 0; transform: translateY(14px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            @media (prefers-reduced-motion: reduce) {
                .gst-hero-fade > * {
                    animation: none !important;
                    transition: none !important;
                    opacity: 1 !important;
                    transform: none !important;
                }
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-white">
            {{-- Shared public header (R3) with privacy-prefixed testids. --}}
            <x-public-header :testIdPrefix="'privacy-'" :isAuthed="auth()->check()" />

            {{-- Guest canvas (indigo-50 → white gradient, faint grid, two
                 blurred orbs). data-testid="guest-canvas" must appear
                 EXACTLY ONCE on this page. The header sits ABOVE this
                 element so its sticky/translucent behaviour is preserved. --}}
            <div class="relative flex-1 w-full bg-gradient-to-b from-indigo-50 to-white"
                 data-testid="guest-canvas">

                <div aria-hidden="true"
                     class="pointer-events-none absolute inset-0 hidden sm:block"
                     style="background-image:
                            linear-gradient(to right, #e5e7eb 1px, transparent 1px),
                            linear-gradient(to bottom, #e5e7eb 1px, transparent 1px);
                            background-size: 38px 38px;
                            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 40%, transparent 80%);
                            mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 40%, transparent 80%);">
                </div>

                <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -top-20 -left-20 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
                    <div class="absolute -bottom-24 -right-20 h-80 w-80 rounded-full bg-violet-500/10 blur-3xl"></div>
                </div>

                <article class="relative max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-6 sm:space-y-8 gst-hero-fade"
                         data-testid="privacy-page">
                    <header>
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 mb-4"
                              data-testid="privacy-icon"
                              aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </span>
                        <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900" data-testid="privacy-title">Privacy</h1>
                        <p class="mt-2 text-base text-gray-600">What we collect, why, how long, and how to ask for removal.</p>
                    </header>

                    {{-- What we collect --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm" data-testid="privacy-section-collect">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900" data-testid="privacy-heading-collect">What we collect</h2>
                        <div class="mt-3 text-sm text-gray-700 space-y-2">
                            <p>For each testimonial submission, we store:</p>
                            <ul class="list-disc pl-5 space-y-1">
                                <li>Name (required, shown publicly)</li>
                                <li>Email address (required, never shown publicly)</li>
                                <li>Postal address (required, never shown publicly)</li>
                                <li>The testimonial text (required, shown publicly when curated)</li>
                                <li>Optional: company name, social URL, profile photo, and star rating, when the Space's field toggles allow them</li>
                                <li>Submission timestamp</li>
                                <li>Whether the respondent gave consent, the consent wording version they agreed to, and the time of consent</li>
                            </ul>
                            <p>Email and postal address are stored so the owner can follow up if needed; they are <strong>never</strong> shown publicly.</p>
                            <p>For a deletion request submitted from the privacy page, we additionally store the email address the person typed, the Space slug they entered, and the optional testimonial id they included, so the owner can find and act on the request.</p>
                        </div>
                    </section>

                    {{-- Why --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm" data-testid="privacy-section-why">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900" data-testid="privacy-heading-why">Why</h2>
                        <p class="mt-3 text-sm text-gray-700">The testimonial text, name, optional company / social URL / photo, optional rating, and the submission timestamp are shown on the owner's curated wall and the embed on third-party sites. The email and address are private to the owner and are used only for follow-up, never displayed.</p>
                    </section>

                    {{-- How long --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm" data-testid="privacy-section-howlong">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900" data-testid="privacy-heading-howlong">How long</h2>
                        <p class="mt-3 text-sm text-gray-700" data-testid="privacy-howlong-text">
                            Soft-deleted testimonials and Spaces are permanently removed after <span data-testid="privacy-retention-days">{{ $retentionDays }}</span> {{ \Illuminate\Support\Str::plural('day', $retentionDays) }}. Space slugs are kept as tombstones so they cannot be re-registered, but the body and photo are removed at the same boundary.
                        </p>
                    </section>

                    {{-- How to request removal --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm" data-testid="privacy-section-removal">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900" data-testid="privacy-heading-removal">How to request removal</h2>
                        <p class="mt-3 text-sm text-gray-700">
                            Submit a deletion request at the
                            <a href="{{ route('privacy.request-deletion') }}" class="underline text-indigo-700 hover:text-indigo-900" data-testid="privacy-removal-link">request deletion form</a>
                            with the email you used and the Space slug.
                        </p>
                    </section>

                    {{-- Consent wording --}}
                    <section class="rounded-2xl border border-gray-200 bg-white p-5 sm:p-6 shadow-sm" data-testid="privacy-section-consent">
                        <h2 class="text-lg sm:text-xl font-bold text-gray-900" data-testid="privacy-heading-consent">Consent wording</h2>
                        <p class="mt-3 text-sm text-gray-700">Every published version of the consent text is listed below. The current version is the wording a respondent agrees to today.</p>
                        <ul class="mt-4 space-y-3" data-testid="privacy-consent-list">
                            @foreach ($versions as $version)
                                <li class="rounded-2xl border {{ $version['isCurrent'] ? 'border-indigo-300 ring-1 ring-indigo-200' : 'border-gray-200' }} bg-white p-4 shadow-sm"
                                    data-testid="privacy-consent-item"
                                    data-version="{{ $version['key'] }}"
                                    data-current="{{ $version['isCurrent'] ? '1' : '0' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900" data-testid="privacy-consent-key">{{ $version['key'] }}</span>
                                        @if ($version['isCurrent'])
                                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700" data-testid="privacy-consent-current">Current</span>
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[11px] font-semibold text-gray-700" data-testid="privacy-consent-previous">Previous</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-line" data-testid="privacy-consent-text">{{ $version['text'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </article>
            </div>

            {{-- Wave 0b — use the shared public footer (F5). --}}
            <x-public-footer :testIdPrefix="'privacy-footer-'" />
        </div>
    </body>
</html>
