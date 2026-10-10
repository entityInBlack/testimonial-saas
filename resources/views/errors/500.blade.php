<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Something went wrong &middot; {{ config('app.name', 'Testimonial SaaS') }}</title>
        {{-- Inline style only. Error pages must render even when
             the asset build is broken: @vite throws if the
             manifest is missing, which would blank the 500 view
             itself. No external CSS or JS. --}}
        <style>
            :root { color-scheme: light; }
            html, body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
            .error-page { min-height: 100vh; display: flex; flex-direction: column; background: #f9fafb; color: #111827; }
            .error-header { background: #fff; border-bottom: 1px solid #e5e7eb; }
            .error-header-inner { max-width: 768px; margin: 0 auto; padding: 12px 16px; display: flex; align-items: center; justify-content: space-between; }
            .error-brand { font-size: 16px; font-weight: 600; color: #111827; text-decoration: none; }
            .error-main { flex: 1; }
            .error-section { background: #fff; }
            .error-section-inner { max-width: 768px; margin: 0 auto; padding: 48px 16px; }
            .error-code { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin: 0; }
            .error-title { margin-top: 8px; font-size: 28px; font-weight: 700; color: #111827; line-height: 1.2; }
            .error-message { margin-top: 16px; font-size: 16px; color: #374151; }
            .error-cta-row { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
            .error-cta-primary { display: inline-flex; align-items: center; padding: 8px 16px; border-radius: 6px; background: #4f46e5; color: #fff; font-size: 14px; font-weight: 500; text-decoration: none; }
            .error-cta-primary:hover { background: #4338ca; }
            .error-cta-secondary { display: inline-flex; align-items: center; padding: 6px 12px; font-size: 14px; color: #374151; text-decoration: none; }
            .error-cta-secondary:hover { color: #111827; }
        </style>
    </head>
    <body>
        <div class="error-page">
            <header class="error-header">
                <div class="error-header-inner">
                    <a href="{{ route('landing') }}" class="error-brand" data-testid="error-brand">
                        {{ config('app.name', 'Testimonial SaaS') }}
                    </a>
                </div>
            </header>
            <main class="error-main">
                <section class="error-section">
                    <div class="error-section-inner">
                        <p class="error-code" data-testid="error-code">500</p>
                        <h1 class="error-title" data-testid="error-title">
                            Something went wrong on our end.
                        </h1>
                        <p class="error-message" data-testid="error-message">
                            Please try again in a moment, or head back to the home page.
                        </p>
                        <div class="error-cta-row">
                            <a href="{{ route('landing') }}" class="error-cta-primary" data-testid="error-cta-home">
                                Back to the home page
                            </a>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </body>
</html>
