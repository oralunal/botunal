<?php

namespace App\Values;

use Carbon\CarbonInterface;

/**
 * The moderation state of a Kick user, derived from their latest ban record.
 */
final class KickUserBanStatus
{
    public const STATUS_CLEAN = 'clean';

    public const STATUS_BANNED = 'banned';

    public const STATUS_TIMED_OUT = 'timed_out';

    public function __construct(
        public readonly string $status,
        public readonly ?CarbonInterface $expiresAt,
    ) {}

    public function isBanned(): bool
    {
        return $this->status === self::STATUS_BANNED;
    }

    public function isTimedOut(): bool
    {
        return $this->status === self::STATUS_TIMED_OUT;
    }

    public function isRestricted(): bool
    {
        return $this->isBanned() || $this->isTimedOut();
    }

    /**
     * @return array{status: string, expires_at: string|null}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'expires_at' => $this->expiresAt?->toIso8601String(),
        ];
    }
}
