<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            /* === Part 2 — App motion =====================================
               Class prefix: `app-` (so it cannot collide with the
               landing's `lvl-` and the guest's `gst-` rules).

               Resting state is the natural state — no opacity, no
               transform on any base rule. The fade-up uses
               `animation ... backwards` so the FIRST keyframe applies
               during the animation delay, and the resting computed
               state is `transform: none; opacity: 1;`. The
               reduced-motion block at the end switches it all off. */

            /* Fade-up for the page content. Applied to the layout's
               <main> inner wrapper, which sits OUTSIDE the Livewire
               components, so Livewire updates never re-run the
               animation. */
            .app-fade {
                animation: app-fade-in 0.5s ease-out backwards;
            }
            @keyframes app-fade-in {
                from { opacity: 0; transform: translateY(12px); }
                to   { opacity: 1; transform: translateY(0); }
            }

            /* Hover lift for primary buttons and link-buttons that
               opt in via .app-lift. The base rule sets only the
               transition; the transform is in the :hover / :focus-
               visible rules so a transform is never present at rest
               (which is required so position: fixed descendants
               like the profile delete modal and the nav dropdown
               keep working). */
            .app-lift {
                transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, background-color 0.18s ease-out;
            }
            .app-lift:hover,
            .app-lift:focus-visible {
                transform: translateY(-2px);
            }

            /* Reduced motion: turn EVERYTHING off (fail-safe) ====== */
            @media (prefers-reduced-motion: reduce) {
                .app-fade,
                .app-lift {
                    animation: none !important;
                    transition: none !important;
                    opacity: 1 !important;
                    transform: none !important;
                }
                .app-lift:hover,
                .app-lift:focus,
                .app-lift:focus-visible {
                    transform: none !important;
                    transition: none !important;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        {{-- Soft canvas: indigo-50 → gray-50 vertical gradient, calm
             working area (no grid, no orbs on purpose). --}}
        <div class="min-h-screen bg-gradient-to-b from-indigo-50 to-gray-50">
            <livewire:layout.navigation />

            <!-- Page Heading (white bar with a light bottom border; no shadow) -->
            @if (isset($header))
                <header class="bg-white/80 border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                <div class="app-fade">
                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
