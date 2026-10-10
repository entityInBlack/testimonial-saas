<?php

namespace App\Services;

use App\Models\Space;

/**
 * Outcome of a `SpaceRestoreService::restore()` call.
 *
 * Either restored (Space is back live) or one of three failure
 * modes: notFound, tombstoned, capReached. No business logic —
 * just a typed DTO. All actual decisions live in
 * `SpaceRestoreService`. The Livewire components branch on the
 * `kind` to decide what to surface to the user.
 */
final class RestoreResult
{
    public const KIND_RESTORED = 'restored';
    public const KIND_NOT_FOUND = 'not_found';
    public const KIND_TOMBSTONED = 'tombstoned';
    public const KIND_CAP_REACHED = 'cap_reached';

    public function __construct(
        public readonly string $kind,
        public readonly ?Space $space = null,
        public readonly int $cap = 0,
    ) {
    }

    public static function restored(Space $space): self
    {
        return new self(self::KIND_RESTORED, $space, 0);
    }

    public static function notFound(): self
    {
        return new self(self::KIND_NOT_FOUND);
    }

    public static function tombstoned(): self
    {
        return new self(self::KIND_TOMBSTONED);
    }

    public static function capReached(int $cap): self
    {
        return new self(self::KIND_CAP_REACHED, null, $cap);
    }

    public function isRestored(): bool
    {
        return $this->kind === self::KIND_RESTORED;
    }

    public function isNotFound(): bool
    {
        return $this->kind === self::KIND_NOT_FOUND;
    }

    public function isTombstoned(): bool
    {
        return $this->kind === self::KIND_TOMBSTONED;
    }

    public function isCapReached(): bool
    {
        return $this->kind === self::KIND_CAP_REACHED;
    }
}
