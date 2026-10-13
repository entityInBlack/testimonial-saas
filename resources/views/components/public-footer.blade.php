{{--
    Wave 0a — shared public footer (R3, design 3.5 #3).
    Used by landing.blade.php, privacy.blade.php, and the guest layout.
    Test ids (default; pass $testIdPrefix to override, e.g. "landing-footer-"):
        - <prefix>                (the <footer> wrapper)
        - <prefix>copy            (the wordmark text in the footer)
        - <prefix>privacy
        - <prefix>request-deletion

    Wave 0b — declare props (F1) so $testIdPrefix does not leak onto the
    rendered <footer> as a stray HTML attribute.
--}}
@props([
    'testIdPrefix' => 'public-footer-',
])
@php
    $prefix = $testIdPrefix;
@endphp
<footer class="bg-white border-t border-gray-200"
        data-testid="{{ $prefix }}">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 text-xs sm:text-sm text-gray-600 flex flex-wrap items-center justify-between gap-2">
        <x-brand-wordmark :context="'public'" :testId="$prefix.'copy'" class="text-xs sm:text-sm font-medium text-gray-600" />
        <span class="flex items-center gap-3 sm:gap-4">
            <a href="{{ route('privacy.show') }}"
               class="hover:text-gray-900"
               data-testid="{{ $prefix }}privacy">Privacy</a>
            <a href="{{ route('privacy.request-deletion') }}"
               class="hover:text-gray-900"
               data-testid="{{ $prefix }}request-deletion">Request deletion</a>
        </span>
    </div>
</footer>
