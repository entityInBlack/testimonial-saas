{{--
    Wave 0a — shared public header (R3, design 3.5 #2).
    Used by landing.blade.php, privacy.blade.php, and the guest layout.
    Layout at every width:
        [wordmark]   [Privacy] [Request deletion]   [Log in] [Create account]
    At <sm: Privacy + Request deletion are hidden via a responsive class
    (they stay in the HTML and they are always in the footer). The
    wordmark + Log in + Create account fit on one line at 320px.

    Test ids (default; pass $testIdPrefix to override, e.g. "landing-"):
        - <prefix>header
        - <prefix>brand  (the wordmark)
        - <prefix>nav
        - <prefix>privacy-link
        - <prefix>request-deletion-link
        - <prefix>dashboard-link   (logged-in only)
        - <prefix>register-link   (guest only)
        - <prefix>login-link       (guest only)

    Wave 0b — declare props (F1) so $isAuthed and $testIdPrefix do not
    leak onto the rendered <header> as stray HTML attributes.
--}}
@props([
    'isAuthed' => null,
    'testIdPrefix' => 'public-header-',
])
@php
    $isAuthed = $isAuthed ?? auth()->check();
    $prefix = $testIdPrefix;
@endphp
<header class="sticky top-0 z-40 bg-white/80 backdrop-blur border-b border-gray-200"
        data-testid="{{ $prefix }}header">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between gap-2">
        <x-brand-wordmark :context="'public'" :testId="$prefix.'brand'" class="shrink-0 text-sm sm:text-base" />
        <nav class="flex items-center gap-3 sm:gap-4 text-xs sm:text-sm"
             data-testid="{{ $prefix }}nav">
            <a href="{{ route('privacy.show') }}"
               class="hidden sm:inline text-gray-600 hover:text-gray-900"
               data-testid="{{ $prefix }}privacy-link">Privacy</a>
            <a href="{{ route('privacy.request-deletion') }}"
               class="hidden sm:inline text-gray-600 hover:text-gray-900"
               data-testid="{{ $prefix }}request-deletion-link">Request deletion</a>
            @if ($isAuthed)
                <a href="{{ route('dashboard') }}"
                   class="text-gray-600 hover:text-gray-900"
                   data-testid="{{ $prefix }}dashboard-link">Dashboard</a>
            @else
                <a href="{{ route('login') }}"
                   class="text-gray-600 hover:text-gray-900"
                   data-testid="{{ $prefix }}login-link">Log in</a>
                <a href="{{ route('register') }}"
                   class="inline-flex items-center min-h-[36px] sm:min-h-0 px-3 py-1.5 rounded-md bg-indigo-600 text-white font-medium hover:bg-indigo-700"
                   data-testid="{{ $prefix }}register-link">Create account</a>
            @endif
        </nav>
    </div>
</header>
