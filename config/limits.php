<?php

/*
 * Free-plan hard caps for v1. Data-model §5.1 is the source of truth.
 *
 *   max_spaces                 = 3
 *   max_testimonials_per_space = 100
 *
 * These are plain constants — no env knob, no Plan enum, no plans table.
 * v1 has one plan. v2 may move this to a plans table or a Cashier lookup.
 */

return [
    'max_spaces' => 3,
    'max_testimonials_per_space' => 100,
];
