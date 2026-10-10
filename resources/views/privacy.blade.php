<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Privacy — {{ config('app.name', 'Testimonial SaaS') }}</title>

        <style>
            :root { color-scheme: light; }
            html, body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
                    <a href="{{ route('landing') }}" class="text-base font-semibold text-gray-900" data-testid="privacy-brand">{{ config('app.name', 'Testimonial SaaS') }}</a>
                    <a href="{{ route('landing') }}" class="text-sm text-gray-600 hover:text-gray-900" data-testid="privacy-home-link">Home</a>
                </div>
            </header>

            <main class="flex-1">
                <article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8"
                         data-testid="privacy-page">
                    <header>
                        <h1 class="text-2xl font-semibold text-gray-900" data-testid="privacy-title">Privacy</h1>
                        <p class="mt-1 text-sm text-gray-600">What we collect, why, how long, and how to ask for removal.</p>
                    </header>

                    {{-- What we collect --}}
                    <section data-testid="privacy-section-collect">
                        <h2 class="text-lg font-semibold text-gray-900" data-testid="privacy-heading-collect">What we collect</h2>
                        <div class="mt-2 text-sm text-gray-700 space-y-2">
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
                    <section data-testid="privacy-section-why">
                        <h2 class="text-lg font-semibold text-gray-900" data-testid="privacy-heading-why">Why</h2>
                        <p class="mt-2 text-sm text-gray-700">The testimonial text, name, optional company / social URL / photo, optional rating, and the submission timestamp are shown on the owner's curated wall and the embed on third-party sites. The email and address are private to the owner and are used only for follow-up, never displayed.</p>
                    </section>

                    {{-- How long --}}
                    <section data-testid="privacy-section-howlong">
                        <h2 class="text-lg font-semibold text-gray-900" data-testid="privacy-heading-howlong">How long</h2>
                        <p class="mt-2 text-sm text-gray-700" data-testid="privacy-howlong-text">
                            Soft-deleted testimonials and Spaces are permanently removed after <span data-testid="privacy-retention-days">{{ $retentionDays }}</span> {{ \Illuminate\Support\Str::plural('day', $retentionDays) }}. Space slugs are kept as tombstones so they cannot be re-registered, but the body and photo are removed at the same boundary.
                        </p>
                    </section>

                    {{-- How to request removal --}}
                    <section data-testid="privacy-section-removal">
                        <h2 class="text-lg font-semibold text-gray-900" data-testid="privacy-heading-removal">How to request removal</h2>
                        <p class="mt-2 text-sm text-gray-700">
                            Submit a deletion request at
                            <a href="{{ route('privacy.request-deletion') }}" class="underline text-indigo-700 hover:text-indigo-900" data-testid="privacy-removal-link">{{ route('privacy.request-deletion') }}</a>
                            with the email you used and the Space slug.
                        </p>
                    </section>

                    {{-- Consent wording --}}
                    <section data-testid="privacy-section-consent">
                        <h2 class="text-lg font-semibold text-gray-900" data-testid="privacy-heading-consent">Consent wording</h2>
                        <p class="mt-2 text-sm text-gray-700">Every published version of the consent text is listed below. The current version is the wording a respondent agrees to today.</p>
                        <ul class="mt-3 space-y-3" data-testid="privacy-consent-list">
                            @foreach ($versions as $version)
                                <li class="bg-white border border-gray-200 rounded-md p-3"
                                    data-testid="privacy-consent-item"
                                    data-version="{{ $version['key'] }}"
                                    data-current="{{ $version['isCurrent'] ? '1' : '0' }}">
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm font-semibold text-gray-900" data-testid="privacy-consent-key">{{ $version['key'] }}</span>
                                        @if ($version['isCurrent'])
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-50 text-emerald-700 border border-emerald-200" data-testid="privacy-consent-current">Current</span>
                                        @else
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-gray-100 text-gray-600 border border-gray-200" data-testid="privacy-consent-previous">Previous</span>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-line" data-testid="privacy-consent-text">{{ $version['text'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </article>
            </main>

            <footer class="bg-white border-t border-gray-200">
                <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-4 text-sm text-gray-600">
                    <a href="{{ route('landing') }}" class="hover:text-gray-900" data-testid="privacy-footer-home">Home</a>
                </div>
            </footer>
        </div>
    </body>
</html>
