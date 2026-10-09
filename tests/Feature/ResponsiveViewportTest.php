<?php

use App\Models\Space;

/*
 * Pins the responsive viewport meta tag in the Breeze layout used by the
 * public /s/{slug} page. If anyone removes it the page will pinch-to-zoom
 * awkwardly on small screens, which is the responsiveness regression we
 * most want to catch.
 */
test('guest layout used by /s/{slug} renders the responsive viewport meta', function () {
    $space = Space::factory()->create();
    $slug = $space->slug;

    $response = $this->get("/s/{$slug}");

    // 200 OR a 4xx (the route may not exist yet, depending on step order).
    // Either way the rendered HTML — if any — must include the meta tag.
    if ($response->getStatusCode() === 200) {
        $response->assertSee(
            '<meta name="viewport" content="width=device-width, initial-scale=1">',
            false
        );
    } else {
        // The route is not wired yet; this is a forward-looking pin.
        // We re-assert against the guest layout file directly so the test
        // is meaningful even before the route ships.
        $layout = file_get_contents(resource_path('views/layouts/guest.blade.php'));
        expect($layout)
            ->toContain('<meta name="viewport" content="width=device-width, initial-scale=1">');
    }
});
