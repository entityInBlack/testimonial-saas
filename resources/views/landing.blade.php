<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Testimonial SaaS') }}</title>

        {{-- No remote fonts, no CDN. The app's Tailwind bundle and the
             system font stack only. Build-order Hard Rule prohibits
             adding new remote resources in Step 8. --}}
        <style>
            :root { color-scheme: light; }
            html, body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        </style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased bg-gray-50">
        <div class="min-h-screen flex flex-col">
            {{-- Top nav --}}
            <header class="bg-white border-b border-gray-200">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
                    <a href="{{ route('landing') }}" class="text-base font-semibold text-gray-900" data-testid="landing-brand">
                        {{ config('app.name', 'Testimonial SaaS') }}
                    </a>
                    <nav class="flex items-center gap-4 text-sm" data-testid="landing-nav">
                        <a href="{{ route('privacy.show') }}" class="text-gray-600 hover:text-gray-900" data-testid="landing-privacy-link">Privacy</a>
                        <a href="{{ route('privacy.request-deletion') }}" class="text-gray-600 hover:text-gray-900" data-testid="landing-request-deletion-link">Request deletion</a>
                        @auth
                            <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900" data-testid="landing-dashboard-link">Dashboard</a>
                        @else
                            <a href="{{ route('register') }}" class="inline-flex items-center px-3 py-1.5 rounded-md bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700" data-testid="landing-register-link">Create account</a>
                        @endauth
                    </nav>
                </div>
            </header>

            <main class="flex-1">
                {{-- Hero --}}
                <section class="bg-white">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 max-w-2xl" data-testid="landing-hero-title">
                            Collect testimonials from your customers and show them on your site.
                        </h1>
                        <p class="mt-4 text-base sm:text-lg text-gray-700 max-w-2xl" data-testid="landing-hero-tagline">
                            Share a public link, collect responses with consent, curate the best ones in an Inbox, and embed a wall of love on any website.
                        </p>
                        <div class="mt-6 flex flex-wrap items-center gap-3">
                            <a href="{{ route('register') }}" class="inline-flex items-center px-5 py-2.5 rounded-md bg-indigo-600 text-white text-sm font-semibold hover:bg-indigo-700" data-testid="landing-cta">
                                Get started
                            </a>
                            <a href="{{ route('privacy.show') }}" class="inline-flex items-center px-3 py-2 text-sm text-gray-700 hover:text-gray-900" data-testid="landing-privacy-cta">
                                Read the privacy policy
                            </a>
                        </div>
                    </div>
                </section>

                {{-- Free plan info --}}
                <section class="bg-gray-50">
                    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        <div class="bg-white border border-gray-200 rounded-lg p-5" data-testid="landing-free-plan">
                            <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Free plan</h2>
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

            <footer class="bg-white border-t border-gray-200">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 text-sm text-gray-600 flex flex-wrap items-center justify-between gap-2">
                    <span data-testid="landing-footer-copy">{{ config('app.name', 'Testimonial SaaS') }}</span>
                    <span class="flex items-center gap-3">
                        <a href="{{ route('privacy.show') }}" class="hover:text-gray-900" data-testid="landing-footer-privacy">Privacy</a>
                        <a href="{{ route('privacy.request-deletion') }}" class="hover:text-gray-900" data-testid="landing-footer-request-deletion">Request deletion</a>
                    </span>
                </div>
            </footer>
        </div>
    </body>
</html>
