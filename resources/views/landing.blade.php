<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Testimonial SaaS') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* === Hero entrance (M1) ====================================== */
            .lvl-hero-fade > * {
                opacity: 0;
                transform: translateY(18px);
                animation: lvl-hero-in 0.7s ease-out forwards;
            }
            .lvl-hero-fade > *:nth-child(1) { animation-delay: 0.00s; }
            .lvl-hero-fade > *:nth-child(2) { animation-delay: 0.12s; }
            .lvl-hero-fade > *:nth-child(3) { animation-delay: 0.24s; }
            .lvl-hero-fade > *:nth-child(4) { animation-delay: 0.36s; }
            .lvl-hero-fade > *:nth-child(5) { animation-delay: 0.48s; }
            @keyframes lvl-hero-in {
                to { opacity: 1; transform: translateY(0); }
            }

            /* === Collage / mobile card reveal (M1) ====================== */
            .lvl-collage-fade,
            .lvl-collage-mobile-fade {
                opacity: 0;
                transform: translateY(12px);
                animation: lvl-collage-in 0.7s ease-out 0.7s forwards;
            }
            @keyframes lvl-collage-in {
                to { opacity: 1; transform: translateY(0); }
            }

            /* === Floating cards (M2) ==================================== */
            .lvl-float-a {
                animation: lvl-float-a 7.5s ease-in-out infinite;
            }
            .lvl-float-b {
                animation: lvl-float-b 8s ease-in-out infinite;
            }
            @keyframes lvl-float-a {
                0%, 100% { transform: translateY(0) rotate(-3deg); }
                50%      { transform: translateY(-9px) rotate(-3deg); }
            }
            @keyframes lvl-float-b {
                0%, 100% { transform: translateY(0) rotate(2deg); }
                50%      { transform: translateY(-10px) rotate(2deg); }
            }

            /* === Buttons / cards hover (M3) ============================ */
            .lvl-btn {
                transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, background-color 0.18s ease-out;
            }
            .lvl-btn:hover,
            .lvl-btn:focus-visible {
                transform: translateY(-2px);
            }
            .lvl-feature-card {
                transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, border-color 0.18s ease-out;
            }
            .lvl-feature-card:hover,
            .lvl-feature-card:focus-within {
                transform: translateY(-3px);
                box-shadow: 0 0 0 2px #c7d2fe; /* indigo-200 */
            }

            /* === Scroll reveal (M4) ===================================== */
            .lvl-reveal {
                opacity: 0;
                transform: translateY(28px) scale(0.97);
                transition: opacity 0.6s ease-out, transform 0.6s ease-out;
            }
            .lvl-reveal.is-visible {
                opacity: 1;
                transform: translateY(0) scale(1);
            }

            /* === Reduced motion: turn EVERYTHING off (fail-safe) ====== */
            @media (prefers-reduced-motion: reduce) {
                .lvl-hero-fade > *,
                .lvl-collage-fade,
                .lvl-collage-mobile-fade,
                .lvl-float-a,
                .lvl-float-b,
                .lvl-reveal {
                    animation: none !important;
                    transition: none !important;
                    opacity: 1 !important;
                    transform: none !important;
                }
                /* Hover/focus must not lift buttons or feature cards either. */
                .lvl-btn,
                .lvl-btn:hover,
                .lvl-btn:focus,
                .lvl-btn:focus-visible,
                .lvl-feature-card,
                .lvl-feature-card:hover,
                .lvl-feature-card:focus,
                .lvl-feature-card:focus-within {
                    transform: none !important;
                    transition: none !important;
                }
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-white">
            {{-- Shared public header (sticky + translucent via class change). --}}
            <x-public-header :testIdPrefix="'landing-'" :isAuthed="auth()->check()" />

            <main class="flex-1">
                {{-- ============================================================
                     B. HERO
                     ============================================================ --}}
                <section class="relative overflow-hidden bg-gradient-to-b from-indigo-50 to-white">
                    {{-- faint decorative blurred orbs --}}
                    <div aria-hidden="true" class="pointer-events-none absolute inset-0 -z-10">
                        <div class="absolute -top-24 -left-24 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
                        <div class="absolute -bottom-24 -right-24 h-80 w-80 rounded-full bg-violet-500/10 blur-3xl"></div>
                    </div>

                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pt-12 pb-16 sm:pt-20 sm:pb-24">
                        <div class="lvl-hero-fade text-center">
                            <h1 class="mx-auto max-w-4xl text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight text-gray-900" data-testid="landing-hero-title">
                                Collect testimonials from your customers
                                <span class="bg-gradient-to-r from-indigo-500 via-indigo-600 to-violet-500 bg-clip-text text-transparent">and show them on your site.</span>
                            </h1>

                            <p class="mx-auto mt-5 max-w-2xl text-base sm:text-lg text-gray-600" data-testid="landing-hero-tagline">
                                Share a public link, collect responses with consent, curate the best ones in an Inbox, and embed a wall of love on any website.
                            </p>

                            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                                <a href="{{ route('register') }}"
                                   class="lvl-btn inline-flex items-center justify-center gap-2 min-h-[44px] px-6 py-3 rounded-xl bg-indigo-600 text-white text-base font-semibold shadow-lg shadow-indigo-600/20 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                   data-testid="landing-cta">
                                    Get started
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5">
                                        <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l5.5 5.25a.75.75 0 010 1.08l-5.5 5.25a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd" />
                                    </svg>
                                </a>
                                <a href="{{ route('privacy.show') }}"
                                   class="lvl-btn inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl bg-white text-gray-800 text-base font-semibold border border-gray-300 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                   data-testid="landing-privacy-cta">
                                    Read the privacy policy
                                </a>
                            </div>

                            <p class="mt-4 text-sm text-gray-600" data-testid="landing-hero-caption">Free plan, no card needed.</p>

                            <div class="mt-7 flex flex-wrap items-center justify-center gap-2" data-testid="landing-chips">
                                <span class="inline-flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700" data-testid="landing-chip">Share a link</span>
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-700" data-testid="landing-chip">Curate in the Inbox</span>
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-700" data-testid="landing-chip">Record consent</span>
                                <span class="inline-flex items-center rounded-full border border-gray-200 bg-white px-3 py-1 text-sm font-medium text-gray-700" data-testid="landing-chip">Embed a wall of love</span>
                            </div>
                        </div>

                        {{-- ========================================================
                             C. COLLAGE (lg and up) — Maya (left) and Daniel (right)
                             ======================================================== --}}
                        <div class="lvl-collage-fade relative mx-auto mt-16 hidden h-[500px] max-w-[70rem] lg:block" data-testid="landing-collage">
                            {{-- faint grid that fades at the edges --}}
                            <div aria-hidden="true"
                                 class="absolute inset-0 rounded-3xl"
                                 style="background-image:
                                    linear-gradient(to right, #e5e7eb 1px, transparent 1px),
                                    linear-gradient(to bottom, #e5e7eb 1px, transparent 1px);
                                    background-size: 38px 38px;
                                    -webkit-mask-image: radial-gradient(ellipse at center, black 55%, transparent 85%);
                                            mask-image: radial-gradient(ellipse at center, black 55%, transparent 85%);">
                            </div>

                            {{-- Inbox faux window (center) --}}
                            <div class="absolute left-1/2 top-1/2 w-[35rem] max-w-full -translate-x-1/2 -translate-y-1/2 rounded-2xl border border-gray-200 bg-white shadow-2xl shadow-gray-900/10">
                                {{-- window top bar --}}
                                <div class="flex items-center justify-between rounded-t-2xl border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                        <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-0.5 text-xs font-medium text-gray-600 border border-gray-200">yourapp.example</span>
                                    <span class="w-10"></span>
                                </div>

                                <div class="flex">
                                    {{-- sidebar --}}
                                    <aside class="w-32 shrink-0 border-r border-gray-200 bg-gray-50 px-3 py-4 text-sm">
                                        <div class="mb-2 text-xs font-semibold text-gray-400 px-2">Workspace</div>
                                        <div class="rounded-md px-2 py-1.5 text-gray-700">Dashboard</div>
                                        <div class="rounded-md bg-indigo-50 px-2 py-1.5 font-semibold text-indigo-700">Inbox</div>
                                        <div class="rounded-md px-2 py-1.5 text-gray-700">Spaces</div>
                                    </aside>

                                    {{-- main --}}
                                    <div class="flex-1 px-5 py-4">
                                        <div class="flex items-center gap-2">
                                            <h3 class="text-base font-semibold text-gray-900">Inbox</h3>
                                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Shiplog Reviews</span>
                                        </div>
                                        <ul class="mt-3 divide-y divide-gray-100">
                                            <li class="flex items-start gap-3 py-2.5">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">MO</span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-900">Maya Okafor</span>
                                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">Live</span>
                                                    </div>
                                                    <p class="truncate text-xs italic text-gray-600">Setup was quick. We shared the link on Friday and the first ones arrived over the weekend.</p>
                                                </div>
                                                <div class="shrink-0" aria-label="5 out of 5 stars" role="img">
                                                    <span class="text-amber-400 text-sm">★★★★★</span>
                                                </div>
                                            </li>
                                            <li class="flex items-start gap-3 py-2.5">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">DR</span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-900">Daniel Reyes</span>
                                                        <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Consented</span>
                                                    </div>
                                                    <p class="truncate text-xs italic text-gray-600">I liked that the consent step was clear, and that I could take it back later.</p>
                                                </div>
                                                <div class="shrink-0" aria-label="4 out of 5 stars" role="img">
                                                    <span class="text-amber-400 text-sm">★★★★</span><span class="text-gray-200 text-sm">★</span>
                                                </div>
                                            </li>
                                            <li class="flex items-start gap-3 py-2.5">
                                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">HS</span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-semibold text-gray-900">Hana Sato</span>
                                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-600">Hidden</span>
                                                    </div>
                                                    <p class="truncate text-xs italic text-gray-600">The form was short and easy to fill in on my phone.</p>
                                                </div>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            {{-- Floating sample card 1: Maya (left, tilted -3deg) --}}
                            <div class="lvl-float-a absolute left-4 top-16 w-72 max-w-[20rem] sm:left-10">
                                <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-900/10" data-testid="landing-sample-card">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                        <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                    </svg>
                                    <blockquote class="mt-2 text-gray-800 leading-relaxed">Setup was quick. We shared the link on Friday and the first testimonials arrived over the weekend.</blockquote>
                                    <div class="mt-3" role="img" aria-label="5 out of 5 stars">
                                        <span class="text-amber-400">★★★★★</span>
                                    </div>
                                    <figcaption class="mt-4 flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">MO</span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Maya Okafor</div>
                                            <div class="text-xs text-gray-600">Founder, Northwind Studio</div>
                                        </div>
                                        <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                    </figcaption>
                                </figure>
                            </div>

                            {{-- Floating sample card 2: Daniel (right, tilted +2deg) --}}
                            <div class="lvl-float-b absolute right-4 bottom-12 w-72 max-w-[20rem] sm:right-10">
                                <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-900/10" data-testid="landing-sample-card">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                        <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                    </svg>
                                    <blockquote class="mt-2 text-gray-800 leading-relaxed">I liked that the consent step was clear. Easy to say yes, and easy to take back later.</blockquote>
                                    <div class="mt-3" role="img" aria-label="4 out of 5 stars">
                                        <span class="text-amber-400">★★★★</span><span class="text-gray-200">★</span>
                                    </div>
                                    <figcaption class="mt-4 flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">DR</span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Daniel Reyes</div>
                                            <div class="text-xs text-gray-600">Customer, Fieldnote</div>
                                        </div>
                                        <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                    </figcaption>
                                </figure>
                            </div>
                        </div>

                        {{-- ========================================================
                             C. COLLAGE — mobile (below lg) — Maya card only
                             ======================================================== --}}
                        <div class="lvl-collage-mobile-fade mt-12 lg:hidden" data-testid="landing-collage-mobile">
                            <div class="mx-auto max-w-[26rem]">
                                <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xl shadow-gray-900/10" data-testid="landing-sample-card">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                        <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                    </svg>
                                    <blockquote class="mt-2 text-gray-800 leading-relaxed">Setup was quick. We shared the link on Friday and the first testimonials arrived over the weekend.</blockquote>
                                    <div class="mt-3" role="img" aria-label="5 out of 5 stars">
                                        <span class="text-amber-400">★★★★★</span>
                                    </div>
                                    <figcaption class="mt-4 flex items-center gap-3">
                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">MO</span>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-gray-900">Maya Okafor</div>
                                            <div class="text-xs text-gray-600">Founder, Northwind Studio</div>
                                        </div>
                                        <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                    </figcaption>
                                </figure>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ============================================================
                     D. EMBED SECTION
                     ============================================================ --}}
                <section class="bg-white" data-testid="landing-embed-section" data-reveal>
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                        <div class="grid grid-cols-1 gap-12 lg:grid-cols-2 lg:gap-16 items-center">
                            <div>
                                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">Add testimonials to your website in minutes</h2>
                                <p class="mt-4 text-lg text-gray-600">No build step and no integration. Paste a small script and a div, and your curated wall appears on the page.</p>

                                <ul class="mt-8 space-y-5">
                                    <li class="flex items-start gap-4">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M3 4.5A1.5 1.5 0 014.5 3h11A1.5 1.5 0 0117 4.5v11a1.5 1.5 0 01-1.5 1.5h-11A1.5 1.5 0 013 15.5v-11zM5 6h10v2H5V6zm0 4h10v2H5v-2zm0 4h6v2H5v-2z"/></svg>
                                        </span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Choose a layout</h3>
                                            <p class="mt-1 text-gray-600">Masonry or carousel, light or dark, with an item limit that suits the page.</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-4">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M8 2a1 1 0 00-1 1v1H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V6a2 2 0 00-2-2h-2V3a1 1 0 10-2 0v1H9V3a1 1 0 00-1-1zM5 8h10v8H5V8z"/></svg>
                                        </span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Copy the snippet</h3>
                                            <p class="mt-1 text-gray-600">The embed builder writes it for you, with a live preview next to it.</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-4">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                            <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        </span>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Go live</h3>
                                            <p class="mt-1 text-gray-600">Only testimonials you curate for the Wall of Love are shown.</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            {{-- Code card --}}
                            <div class="rounded-2xl bg-gray-900 shadow-2xl shadow-gray-900/20 overflow-hidden">
                                <div class="flex items-center gap-1.5 border-b border-gray-800 bg-gray-800/80 px-4 py-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span>
                                    <span class="h-2.5 w-2.5 rounded-full bg-emerald-400"></span>
                                    <span class="ml-3 text-xs font-medium text-gray-400">index.html</span>
                                </div>
                                <div class="overflow-x-auto">
                                    <pre data-testid="landing-embed-snippet" class="px-5 py-5 text-sm leading-relaxed text-gray-100 font-mono whitespace-pre"><code><span class="text-indigo-300">&lt;div</span> <span class="text-emerald-400">data-testimonial-space</span>=<span class="text-amber-400">"YOUR_SPACE_ID"</span><span class="text-indigo-300">&gt;&lt;/div&gt;</span>
<span class="text-indigo-300">&lt;script</span> <span class="text-emerald-400">src</span>=<span class="text-amber-400">"{{ rtrim((string) config('app.url'), '/') }}/embed.js"</span> <span class="text-emerald-400">async</span><span class="text-indigo-300">&gt;&lt;/script&gt;</span></code></pre>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ============================================================
                     E. WALL SECTION
                     ============================================================ --}}
                <section class="bg-gray-50 border-y border-gray-200" data-testid="landing-wall-section" data-reveal>
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                        <div class="grid grid-cols-1 gap-12 lg:grid-cols-5 lg:gap-12">
                            <div class="lg:col-span-2">
                                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900">Collect it, curate it, show it</h2>
                                <p class="mt-4 text-lg text-gray-600">Everything a small team needs to turn happy customers into social proof, with consent recorded along the way.</p>

                                <ul class="mt-8 space-y-5">
                                    <li class="flex items-start gap-3">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">A public link for every Space</h3>
                                            <p class="mt-1 text-gray-600">Share one link and customers answer in their own time. Turn on only the fields you need.</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">An Inbox you control</h3>
                                            <p class="mt-1 text-gray-600">Favorite the best, hide the rest, and restore deleted ones during the retention window.</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">Consent on the record</h3>
                                            <p class="mt-1 text-gray-600">Each testimonial stores the consent wording its author agreed to, and consent can be withdrawn at any time.</p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3">
                                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-6 w-6 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-900">A wall you can embed anywhere</h3>
                                            <p class="mt-1 text-gray-600">Pick masonry or carousel, light or dark, and paste one snippet.</p>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="lg:col-span-3 lg:sticky lg:top-24 self-start">
                                <div class="rounded-3xl border border-indigo-200 bg-indigo-50 p-6 sm:p-8">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                        {{-- Column 1: Hana --}}
                                        <div class="sm:mt-0">
                                            <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="landing-sample-card">
                                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                                    <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                                </svg>
                                                <blockquote class="mt-2 text-gray-800 leading-relaxed">Clear form, took a minute, and I could see exactly what I was agreeing to.</blockquote>
                                                <figcaption class="mt-4 flex items-center gap-3">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">HS</span>
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-semibold text-gray-900">Hana Sato</div>
                                                        <div class="text-xs text-gray-600">Customer, Harbor Yoga</div>
                                                    </div>
                                                    <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                                </figcaption>
                                            </figure>
                                        </div>

                                        {{-- Column 2: Omar + Lena (offset down) --}}
                                        <div class="sm:mt-10">
                                            <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="landing-sample-card">
                                                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                                    <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                                </svg>
                                                <blockquote class="mt-2 text-gray-800 leading-relaxed">Simple and quick.</blockquote>
                                                <figcaption class="mt-4 flex items-center gap-3">
                                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-xs font-semibold text-emerald-700">OH</span>
                                                    <div class="min-w-0">
                                                        <div class="text-sm font-semibold text-gray-900">Omar Haddad</div>
                                                        <div class="text-xs text-gray-600">Customer</div>
                                                    </div>
                                                    <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                                </figcaption>
                                            </figure>

                                            <div class="mt-5">
                                                <figure class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" data-testid="landing-sample-card">
                                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-8 w-8 text-indigo-200">
                                                        <path d="M7 7h3l-2 5H6v3h4v-3l2-5H7V7zm10 0h-3l-2 5h-2v3h4v-3l2-5h-1V7z"/>
                                                    </svg>
                                                    <blockquote class="mt-2 text-gray-800 leading-relaxed">We dropped the wall on our product page and it just worked. New favorites show up on their own.</blockquote>
                                                    <div class="mt-3" role="img" aria-label="5 out of 5 stars">
                                                        <span class="text-amber-400">★★★★★</span>
                                                    </div>
                                                    <figcaption class="mt-4 flex items-center gap-3">
                                                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-semibold text-indigo-700">LF</span>
                                                        <div class="min-w-0">
                                                            <div class="text-sm font-semibold text-gray-900">Lena Fischer</div>
                                                            <div class="text-xs text-gray-600">Owner, Lumen Bakery</div>
                                                        </div>
                                                        <span class="ml-auto inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-[10px] font-semibold text-gray-600" data-testid="landing-sample-label">Sample</span>
                                                    </figcaption>
                                                </figure>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- ============================================================
                     F. FEATURES
                     ============================================================ --}}
                <section class="bg-white">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                        <h2 class="text-center text-3xl sm:text-4xl font-extrabold tracking-tight text-gray-900" data-testid="landing-features-title">Features</h2>

                        <ul class="mt-10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5" data-testid="landing-features-list" data-reveal>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-spaces">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M10.394 2.08a1 1 0 00-.788 0l-7 3a1 1 0 000 1.84L5 8.241V12.5a1 1 0 00.293.707l3 3a1 1 0 001.414 0l3-3A1 1 0 0013 12.5V8.241l2.394-1.32a1 1 0 000-1.84l-7-3zM6 9.03l3 1.286v2.097l-3-3V9.03zm8 0v.083l-3 3V10.32l3-1.286z"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Spaces</h3>
                                <p class="mt-2 text-gray-600">One Space per page or service. Each has its own public link, theme, and field toggles.</p>
                            </li>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-inbox">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm0 2h12v2H4V5zm0 4h12v6H4V9z"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Inbox</h3>
                                <p class="mt-2 text-gray-600">Read every response, favorite the best, curate a Wall of Love, hide the rest, send to Trash.</p>
                            </li>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-consent">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M10 1.5l7 3.25v4.5c0 4.418-3.582 8.25-7 9.25-3.418-1-7-4.832-7-9.25v-4.5l7-3.25zm-.707 12.707l5-5a1 1 0 10-1.414-1.414l-4.293 4.293-1.793-1.793a1 1 0 10-1.414 1.414l2.5 2.5a1 1 0 001.414 0z" clip-rule="evenodd"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Versioned consent</h3>
                                <p class="mt-2 text-gray-600">Every published testimonial records which consent wording the respondent agreed to. Consent can be withdrawn at any time.</p>
                            </li>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-embed">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 010 1.06L3.56 9l2.72 2.72a.75.75 0 11-1.06 1.06l-3.25-3.25a.75.75 0 010-1.06l3.25-3.25a.75.75 0 011.06 0zm7.44 0a.75.75 0 011.06 0l3.25 3.25a.75.75 0 010 1.06l-3.25 3.25a.75.75 0 11-1.06-1.06L16.44 9l-2.72-2.72a.75.75 0 010-1.06zM9.97 4.47a.75.75 0 011.06.03l.04.04 3 9a.75.75 0 11-1.41.52l-2.5-7.5-2.5 7.5a.75.75 0 11-1.41-.52l3-9a.75.75 0 01.72-.57z" clip-rule="evenodd"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Embed</h3>
                                <p class="mt-2 text-gray-600">A tiny script and a <code>&lt;div&gt;</code> paste a masonry or carousel wall on any third-party site.</p>
                            </li>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-deletion">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.53l.841-10.52.149.023a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Deletion requests</h3>
                                <p class="mt-2 text-gray-600">Respondents can ask for removal from the privacy page. The owner's dashboard shows open requests.</p>
                            </li>
                            <li class="lvl-feature-card rounded-2xl border border-gray-200 bg-white p-6 shadow-sm" data-testid="landing-feature-purge">
                                <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm.75-13a.75.75 0 00-1.5 0v5c0 .2.08.39.22.53l3 3a.75.75 0 101.06-1.06L10.75 9.69V5z" clip-rule="evenodd"/></svg>
                                </span>
                                <h3 class="mt-4 text-lg font-semibold text-gray-900">Retention policy</h3>
                                <p class="mt-2 text-gray-600">Soft-deleted data is automatically purged after a configurable retention window. Tombstones claim slugs forever.</p>
                            </li>
                        </ul>
                    </div>
                </section>

                {{-- ============================================================
                     G. FREE PLAN
                     ============================================================ --}}
                <section class="bg-indigo-50">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                        <div class="mx-auto max-w-[45rem] rounded-3xl border-2 border-indigo-600 bg-white p-8 sm:p-10 shadow-xl shadow-indigo-600/10" data-testid="landing-free-plan" data-reveal>
                            <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-indigo-700" data-testid="landing-free-plan-heading">Free plan</h2>
                            <p class="mt-3 text-xl sm:text-2xl font-extrabold text-gray-900" data-testid="landing-free-plan-line">
                                {{ $maxSpaces }} Spaces
                                &middot;
                                {{ $maxTestimonialsPerSpace }} testimonials per Space
                            </p>
                            <ul class="mt-6 space-y-3">
                                <li class="flex items-start gap-3">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span class="text-gray-800">A public submission link for each Space</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span class="text-gray-800">An Inbox with consent records</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-5 w-5 shrink-0 text-indigo-600"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    <span class="text-gray-800">An embeddable wall of love</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                {{-- ============================================================
                     H. CLOSING BAND
                     ============================================================ --}}
                <section class="bg-white">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20">
                        <section class="relative overflow-hidden rounded-3xl bg-indigo-600 px-6 py-14 sm:px-12 sm:py-20 text-center shadow-xl shadow-indigo-600/20" data-testid="landing-final-cta" data-reveal>
                            <div aria-hidden="true" class="pointer-events-none absolute -left-16 -top-16 h-48 w-48 rounded-full bg-white/20 blur-2xl"></div>
                            <div aria-hidden="true" class="pointer-events-none absolute -right-16 -bottom-16 h-56 w-56 rounded-full bg-violet-500/40 blur-3xl"></div>

                            <h2 class="relative text-3xl sm:text-4xl font-extrabold tracking-tight text-white">Ready to collect your first testimonial?</h2>
                            <p class="relative mx-auto mt-4 max-w-2xl text-base sm:text-lg text-indigo-100">Create a free account, share your Space link, and curate what comes back.</p>

                            <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
                                <a href="{{ route('register') }}"
                                   class="lvl-btn inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl bg-white text-indigo-700 text-base font-semibold shadow-lg shadow-indigo-900/20 hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600"
                                   data-testid="landing-final-cta-register">Create account</a>
                                <a href="{{ route('privacy.show') }}"
                                   class="lvl-btn inline-flex items-center justify-center min-h-[44px] px-6 py-3 rounded-xl border border-white/50 bg-transparent text-white text-base font-semibold hover:bg-white/10 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-indigo-600"
                                   data-testid="landing-final-privacy">Read the privacy policy</a>
                            </div>
                        </section>
                    </div>
                </section>
            </main>

            <x-public-footer :testIdPrefix="'landing-footer-'" />
        </div>

        {{-- ============================================================
             Scroll-reveal logic. One small inline script, no library.
             Order matters for the fail-safe:
               1. Bail out early if IntersectionObserver is missing.
               2. Construct the observer FIRST (no element is hidden yet).
               3. THEN add .lvl-reveal to each target and observe it.
             If any step throws, the catch block logs the error AND
             strips BOTH classes from every reveal target, so every
             section ends up fully visible. The static reveal CSS only
             hides things once the script has added .lvl-reveal, so
             with JS off nothing is ever hidden.
             ============================================================ --}}
        <script>
            (function () {
                try {
                    if (typeof window === 'undefined' || !('IntersectionObserver' in window)) return;
                    var reveals = document.querySelectorAll('[data-reveal]');
                    var io = new IntersectionObserver(function (entries) {
                        entries.forEach(function (entry) {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-visible');
                                io.unobserve(entry.target);
                            }
                        });
                    }, { threshold: 0.1 });
                    for (var i = 0; i < reveals.length; i++) {
                        reveals[i].classList.add('lvl-reveal');
                        io.observe(reveals[i]);
                    }
                } catch (e) {
                    console.error('Scroll-reveal: failed to initialise, revealing everything.', e);
                    var reveals2 = document.querySelectorAll('[data-reveal]');
                    for (var j = 0; j < reveals2.length; j++) {
                        reveals2[j].classList.remove('lvl-reveal');
                        reveals2[j].classList.remove('is-visible');
                    }
                }
            })();
        </script>
    </body>
</html>
