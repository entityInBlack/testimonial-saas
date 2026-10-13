<?php
/*
    Wave 0a — shared guest layout (R4).

    Renders the public guest canvas (indigo-50 → white → gray-50 vertical
    gradient with a faint decorative grid + two blurred orbs) with the
    public header + footer from R3, and a default "primary" white card
    (rounded-2xl border, soft indigo-tinted shadow) around the page slot
    for the auth pages (login, register, forgot, reset, confirm, verify)
    that rely on the card today.

    Opt-out for pages that draw their own card (wave 0b uses this for
    /s/{slug} and the deletion form):

        A page can pass either or both of these layout params via the
        Livewire Layout attribute, e.g.

            #[Layout('layouts.guest', ['cardless' => true])]
            #[Layout('layouts.guest', ['contentMaxWidth' => 'max-w-2xl'])]

        When $cardless is truthy, the default white card is suppressed
        and the page takes full responsibility for its own background
        and width. When $contentMaxWidth is set, it overrides the
        default max-w-md (any Tailwind max-w-* class is accepted).

    Auth pages (login, register, forgot, reset, confirm, verify) keep
    the existing Layout('layouts.guest') attribute with no params and
    therefore get the default card.

    Invariants:
        - The viewport meta tag is exactly as today
          (<meta name="viewport" content="width=device-width, initial-scale=1">)
        - No remote font link (D1).
        - Minimum 16 px side padding at every width (px-4 sm:px-6 lg:px-8).
        - Exactly one <div data-testid="guest-canvas"> on every page
          (the canvas wrapper around the main content). It is the
          stacking context that hosts the gradient, the optional
          decorative grid, and the two blurred orbs.
        - When the default (non-cardless) branch renders, the
          <div data-testid="guest-card"> appears exactly once and
          carries the primary-card look.
*/
?>
@php
    $isCardless = ! empty($cardless);
    $contentMaxWidth = ! empty($contentMaxWidth) ? $contentMaxWidth : 'max-w-md';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Testimonial SaaS') }}{{ isset($titleSuffix) ? ' — '.$titleSuffix : '' }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* === Guest canvas entrance + small motion (Part 1b) =======
               All new classes are prefixed `gst-` so they cannot
               collide with landing's `lvl-` classes. The animated
               elements rest in their NATURAL state (no opacity,
               no transform set on the base rule); only the animation
               describes the way IN, with `backwards` fill so the
               hidden/offset first frame applies during each child's
               animation-delay. The resting computed state must be
               `transform: none` and `opacity: 1`. The
               `prefers-reduced-motion` block at the end switches
               everything off, exactly like landing does. ============== */
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

            /* Subtle "pop" for the thanks check chip. */
            .gst-pop {
                animation: gst-pop-in 0.4s ease-out 0.05s backwards;
            }
            @keyframes gst-pop-in {
                from { opacity: 0; transform: scale(0.85); }
                to   { opacity: 1; transform: scale(1); }
            }

            /* Primary buttons on guest pages: small hover lift
               (matches landing's lvl-btn). */
            .gst-btn {
                transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, background-color 0.18s ease-out;
            }
            .gst-btn:hover,
            .gst-btn:focus-visible {
                transform: translateY(-2px);
            }

            /* Reduced motion: turn EVERYTHING off (fail-safe) ====== */
            @media (prefers-reduced-motion: reduce) {
                .gst-hero-fade > *,
                .gst-pop {
                    animation: none !important;
                    transition: none !important;
                    opacity: 1 !important;
                    transform: none !important;
                }
                .gst-btn,
                .gst-btn:hover,
                .gst-btn:focus,
                .gst-btn:focus-visible {
                    transform: none !important;
                    transition: none !important;
                }
            }
        </style>
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-white">
            <x-public-header :isAuthed="auth()->check()" />

            {{-- Guest canvas: gradient background, faint decorative
                 grid, two blurred orbs. data-testid="guest-canvas"
                 must appear EXACTLY ONCE on every guest page.
                 Layering (back to front): the gradient is the
                 element's own background, then the absolutely
                 positioned grid (pointer-events-none) and the two
                 orbs, then <main class="relative"> which appears
                 LATER in the DOM and therefore paints on top. --}}
            <div class="relative flex-1 w-full bg-gradient-to-b from-indigo-50 to-white"
                 data-testid="guest-canvas">

                {{-- faint 38px grid that fades at the edges --}}
                <div aria-hidden="true"
                     class="pointer-events-none absolute inset-0 hidden sm:block"
                     style="background-image:
                            linear-gradient(to right, #e5e7eb 1px, transparent 1px),
                            linear-gradient(to bottom, #e5e7eb 1px, transparent 1px);
                            background-size: 38px 38px;
                            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 40%, transparent 80%);
                            mask-image: radial-gradient(ellipse 80% 60% at 50% 30%, #000 40%, transparent 80%);">
                </div>

                {{-- decorative blurred orbs --}}
                <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                    <div class="absolute -top-20 -left-20 h-72 w-72 rounded-full bg-indigo-200/40 blur-3xl"></div>
                    <div class="absolute -bottom-24 -right-20 h-80 w-80 rounded-full bg-violet-500/10 blur-3xl"></div>
                </div>

                {{-- Content sits on top of the canvas backdrop. --}}
                <main class="relative w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
                    <div class="{{ $contentMaxWidth }} mx-auto gst-hero-fade">
                        @if ($isCardless)
                            {{ $slot }}
                        @else
                            <div class="rounded-2xl border border-gray-200 bg-white p-6 sm:p-8 shadow-xl shadow-indigo-600/10"
                                 data-testid="guest-card">
                                {{ $slot }}
                            </div>
                        @endif
                    </div>
                </main>
            </div>

            <x-public-footer />
        </div>
    </body>
</html>
