<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Testimonial SaaS') }}</title>

        {{-- Wave 0a — D1: no remote fonts. The Tailwind sans family is
             the system stack (see tailwind.config.js), which is what the
             landing/privacy pages already use. --}}

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col bg-gray-100">
            {{-- Shared public header (R3) with landing-prefixed testids so
                 existing assertions (landing-brand, landing-nav,
                 landing-privacy-link, landing-request-deletion-link,
                 landing-dashboard-link, landing-register-link) keep their
                 same-meaning elements. --}}
            <x-public-header :testIdPrefix="'landing-'" :isAuthed="auth()->check()" />

            <main class="flex-1">
                {{-- Hero --}}
                <section class="bg-white">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16">
                        <h1 class="text-2xl sm:text-4xl font-bold text-gray-900 max-w-2xl" data-testid="landing-hero-title">
                            Collect testimonials from your customers and show them on your site.
                        </h1>
                        <p class="mt-4 text-base sm:text-lg text-gray-700 max-w-2xl" data-testid="landing-hero-tagline">
                            Share a public link, collect responses with consent, curate the best ones in an Inbox, and embed a wall of love on any website.
                        </p>
                        <div class="mt-6 flex flex-wrap items-start gap-x-3 gap-y-2">
                            <a href="{{ route('register') }}" class="inline-flex items-center min-h-[44px] px-5 py-2.5 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700" data-testid="landing-cta">
                                Get started
                            </a>
                            <a href="{{ route('privacy.show') }}" class="inline-flex items-center min-h-[44px] py-2.5 text-sm text-gray-700 hover:text-gray-900" data-testid="landing-privacy-cta">
                                Read the privacy policy
                            </a>
                        </div>
                    </div>
                </section>

                {{-- Free plan info --}}
                <section class="bg-gray-100">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div class="bg-white border border-gray-200 rounded-lg p-5" data-testid="landing-free-plan">
                            <h2 class="text-sm font-semibold text-gray-700" data-testid="landing-free-plan-heading">Free plan</h2>
                            <p class="mt-2 text-base text-gray-900" data-testid="landing-free-plan-line">
                                {{ $maxSpaces }} Spaces
                                &middot;
                                {{ $maxTestimonialsPerSpace }} testimonials per Space
                            </p>
                        </div>
                    </div>
                </section>

                {{-- Features --}}
                <section class="bg-white">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
                        <h2 class="text-2xl font-semibold text-gray-900" data-testid="landing-features-title">Features</h2>
                        <ul class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-700" data-testid="landing-features-list">
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-spaces">
                                <h3 class="text-sm font-semibold text-gray-900">Spaces</h3>
                                <p class="mt-1">One Space per page or service. Each has its own public link, theme, and field toggles.</p>
                            </li>
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-inbox">
                                <h3 class="text-sm font-semibold text-gray-900">Inbox</h3>
                                <p class="mt-1">Read every response, favorite the best, curate a Wall of Love, hide the rest, send to Trash.</p>
                            </li>
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-consent">
                                <h3 class="text-sm font-semibold text-gray-900">Versioned consent</h3>
                                <p class="mt-1">Every published testimonial records which consent wording the respondent agreed to. Consent can be withdrawn at any time.</p>
                            </li>
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-embed">
                                <h3 class="text-sm font-semibold text-gray-900">Embed</h3>
                                <p class="mt-1">A tiny script and a <code>&lt;div&gt;</code> paste a masonry or carousel wall on any third-party site.</p>
                            </li>
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-deletion">
                                <h3 class="text-sm font-semibold text-gray-900">Deletion requests</h3>
                                <p class="mt-1">Respondents can ask for removal from the privacy page. The owner's dashboard shows open requests.</p>
                            </li>
                            <li class="bg-gray-50 rounded-lg p-4" data-testid="landing-feature-purge">
                                <h3 class="text-sm font-semibold text-gray-900">Retention policy</h3>
                                <p class="mt-1">Soft-deleted data is automatically purged after a configurable retention window. Tombstones claim slugs forever.</p>
                            </li>
                        </ul>
                    </div>
                </section>
            </main>

            <x-public-footer :testIdPrefix="'landing-footer-'" />
        </div>
    </body>
</html>