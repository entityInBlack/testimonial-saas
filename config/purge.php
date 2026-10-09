<?php

/*
 * Soft-delete + 30-day purge policy. The schedule command is registered
 * separately (Step 7); this file only holds the policy numbers.
 *
 * Tombstones are kept forever (audit), but the *body* of the testimonial
 * and the photo file on disk are hard-deleted after `retention_days`.
 */

return [
    /*
     * Days between soft-delete and hard-delete of personal data.
     * Photo files on the `public` disk are removed at the same boundary.
     */
    'retention_days' => (int) env('PURGE_RETENTION_DAYS', 30),

    /*
     * Chunk size used by the purge command. One transaction per chunk
     * so a partially-run command never corrupts a testimonial.
     */
    'batch_size' => (int) env('PURGE_BATCH_SIZE', 50),

    /*
     * Soft warning before deletion. Displayed on the deletion-requests
     * inbox so an owner sees "deletes in 5 days" if they undo it.
     */
    'soft_warning_days' => (int) env('PURGE_SOFT_WARNING_DAYS', 5),
];
