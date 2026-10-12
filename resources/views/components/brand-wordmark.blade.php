{{--
    Wave 0a — shared brand wordmark (D2).
    Renders a text wordmark with the app's name and an accessible name.
    Link target is context-driven:
        $context = 'public' (default) → route('landing')  (guest / public)
        $context = 'app'              → route('dashboard') (app / logged in)
    All callers share a single data-testid ("brand-wordmark") so a global
    test can pin the wordmark; pages that need a per-page test id can
    pass $testId to override.
--}}
@php
    $context = $context ?? 'public';
    $href = $context === 'app' ? route('dashboard') : route('landing');
    $testId = $testId ?? 'brand-wordmark';
@endphp
<a href="{{ $href }}"
   {{ $attributes->merge(['class' => 'text-base font-semibold text-gray-900 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 rounded-sm']) }}
   data-testid="{{ $testId }}"
   aria-label="{{ config('app.name') }}">
    {{ config('app.name') }}
</a>
