<?php

/*
 * Rotating copy the public submission page shows on success.
 * One entry is picked at random per submission. Easy to extend
 * without code changes — the controller reads via
 * `config('messages.submission_thanks')`.
 */
return [
    'submission_thanks' => [
        "You're officially awesome. 🚀",
        "That's the kind of feedback that fuels us. ☕",
        'High five from the team. 🙌',
        'We just did a little happy dance. 💃',
        'You made our day. ✨',
    ],
];
