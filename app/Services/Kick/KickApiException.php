<?php

namespace App\Services\Kick;

use Illuminate\Support\Str;
use RuntimeException;

/**
 * A failed Kick API response. Carries the request context so callers can
 * translate Kick's terse errors into actionable messages.
 *
 * Extends RuntimeException so existing broad catches keep working.
 */
class KickApiException extends RuntimeException
{
    public function __construct(
        public readonly string $apiMethod,
        public readonly string $apiPath,
        public readonly int $statusCode,
        public readonly string $responseBody,
    ) {
        parent::__construct(
            "Kick API {$apiMethod} {$apiPath} failed [{$statusCode}]: {$responseBody}"
        );
    }

    /**
     * A human-friendly explanation for the dashboard.
     */
    public function userMessage(): string
    {
        // Kick answers a moderation ban/timeout/unban against the broadcaster,
        // a moderator, or another privileged account with a generic 400
        // "Invalid request" — the request itself is well-formed.
        if ($this->statusCode === 400 && str_contains($this->apiPath, '/moderation/bans')) {
            return __("Kick rejected this action. The broadcaster, moderators, and other privileged accounts can't be banned, timed out, or unbanned through the API — choose a regular viewer.");
        }

        return __('Kick API error (:status): :body', [
            'status' => $this->statusCode,
            'body' => Str::limit($this->responseBody, 180, ''),
        ]);
    }
}
