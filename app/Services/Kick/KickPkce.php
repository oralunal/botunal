<?php

namespace App\Services\Kick;

use Illuminate\Support\Str;

/**
 * Helpers for the OAuth 2.1 PKCE flow (RFC 7636) used by Kick.
 */
class KickPkce
{
    /**
     * Generate a high-entropy code verifier.
     */
    public static function generateVerifier(): string
    {
        return Str::random(96);
    }

    /**
     * Derive the S256 code challenge for a given verifier.
     */
    public static function challenge(string $verifier): string
    {
        return self::base64UrlEncode(hash('sha256', $verifier, true));
    }

    /**
     * Generate a random anti-forgery state token.
     */
    public static function state(): string
    {
        return Str::random(40);
    }

    /**
     * Base64 URL-safe encoding without padding.
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
