<?php

/*
 * Versioned consent wording. The current version is what new submissions
 * record against. Published versions are never edited — change the
 * wording by adding a new version key.
 *
 * Data-model §7.4: a published version's wording is the audit-trail
 * reference for every testimonial that recorded it in
 * `consent_text_version`. Editing v1 in place would silently rewrite
 * what respondents agreed to, so the contract is: don't.
 */

return [
    'current' => 'v1',

    'texts' => [
        'v1' => 'I give permission for this testimonial (my name, my message, and — if I provided them — my photo, social URL, and company) to be displayed publicly on the testimonial wall and embedded on third-party sites. I can withdraw consent and request deletion at any time.',
    ],
];
