<?php

/*
 * Default values for the embed configuration when no row exists in
 * `embed_configurations` for a Space.
 *
 * Single source of truth for both the public API and the embed
 * builder. Hard rule 9 (build order) says the API server-side clamp
 * is the source of truth, so the builder reads from the same place.
 *
 * Data-model §3.4 / build-order Step 7.
 */

return [
    'layout'             => 'masonry',
    'dark_mode'          => false,
    'animation_enabled'  => true,
    'background_color'   => null,
    'show_rating'        => true,
    'item_limit'         => 12,
];
