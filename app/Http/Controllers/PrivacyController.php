<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Public privacy and deletion pages (Step 8).
 *
 * The landing page at `/` and the privacy + deletion-request pages
 * are all served as plain Blade views. They need NO auth and NO DB
 * writes (apart from the deletion-request handler which is a
 * separate Livewire component, see PartC/DeletionRequestForm).
 *
 * The landing page reads `config('limits.max_spaces')` and
 * `config('limits.max_testimonials_per_space')` directly so the
 * copy tracks the config without re-deployment.
 *
 * The /privacy page lists EVERY version key in `config('consent.texts')`
 * (the current key labelled "Current", all others "Previous"), and
 * the retention value from `config('purge.retention_days')`.
 *
 * Hard rules honoured:
 *   - No /billing, no "Pro" / "Stripe" / "Upgrade" / "billing" text
 *     in any user-facing copy on these pages.
 *   - No "GDPR compliant" / legal-compliance claims (legal review is
 *     deferred to a v2 item per build-order Hard Rule 15 / Step 8
 *     scope).
 */
class PrivacyController
{
    /**
     * The public landing page (`/`).
     */
    public function landing(): View
    {
        $maxSpaces = (int) config('limits.max_spaces', 3);
        $maxTestimonialsPerSpace = (int) config('limits.max_testimonials_per_space', 100);

        return view('landing', [
            'maxSpaces' => $maxSpaces,
            'maxTestimonialsPerSpace' => $maxTestimonialsPerSpace,
        ]);
    }

    /**
     * The `/privacy` static page.
     */
    public function show(): View
    {
        $retentionDays = (int) config('purge.retention_days', 30);
        $current = (string) config('consent.current', 'v1');
        $texts = (array) config('consent.texts', []);

        $versions = [];
        foreach ($texts as $key => $text) {
            $versions[] = [
                'key' => (string) $key,
                'text' => (string) $text,
                'isCurrent' => (string) $key === $current,
            ];
        }

        return view('privacy', [
            'retentionDays' => $retentionDays,
            'currentVersion' => $current,
            'versions' => $versions,
        ]);
    }
}
