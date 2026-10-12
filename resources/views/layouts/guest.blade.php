<?php
/*
    Wave 0a — shared guest layout (R4).

    Renders the gray-100 page canvas with the public header + footer from
    R3, and a default white card (max-w-md) around the page slot for the
    auth pages (login, register, forgot, reset, confirm, verify) that
    rely on the card today.

    Opt-out for pages that draw their own card (wave 0b will use this for
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
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-gray-100">
            <x-public-header :isAuthed="auth()->check()" />

            <main class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
                <div class="{{ $contentMaxWidth }} mx-auto">
                    @if ($isCardless)
                        {{ $slot }}
                    @else
                        <div class="bg-white shadow-sm rounded-lg p-6 sm:p-8"
                             data-testid="guest-card">
                            {{ $slot }}
                        </div>
                    @endif
                </div>
            </main>

            <x-public-footer />
        </div>
    </body>
</html>