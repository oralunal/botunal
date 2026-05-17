<?php

namespace App\Services\Kick;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies inbound Kick webhook signatures.
 *
 * Kick signs "{messageId}.{timestamp}.{rawBody}" with its private key using
 * RSA-SHA256 (PKCS1v15). The matching public key is served, unauthenticated,
 * from the Kick public API and cached locally.
 */
class KickSignatureVerifier
{
    private const CACHE_KEY = 'kick:public_key';

    /**
     * Verify a webhook signature. Returns false on any failure.
     */
    public function verify(string $messageId, string $timestamp, string $rawBody, string $signatureBase64): bool
    {
        $signature = base64_decode($signatureBase64, true);

        if ($signature === false || $signature === '') {
            return false;
        }

        if (! $this->withinTolerance($timestamp)) {
            return false;
        }

        $payload = "{$messageId}.{$timestamp}.{$rawBody}";

        if ($this->check($payload, $signature, $this->publicKey())) {
            return true;
        }

        // Key may have rotated; refetch once before giving up.
        return $this->check($payload, $signature, $this->publicKey(forceRefresh: true));
    }

    /**
     * Perform the openssl signature check.
     */
    private function check(string $payload, string $signature, ?string $publicKeyPem): bool
    {
        if ($publicKeyPem === null) {
            return false;
        }

        return openssl_verify($payload, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Reject events whose timestamp is outside the allowed replay window.
     */
    private function withinTolerance(string $timestamp): bool
    {
        $tolerance = (int) config('services.kick.webhook_tolerance_seconds', 300);

        $time = strtotime($timestamp);

        if ($time === false) {
            return false;
        }

        return abs(now()->getTimestamp() - $time) <= $tolerance;
    }

    /**
     * Fetch (and cache) the Kick public key in PEM form.
     */
    private function publicKey(bool $forceRefresh = false): ?string
    {
        if ($forceRefresh) {
            Cache::forget(self::CACHE_KEY);
        }

        $ttl = (int) config('services.kick.public_key_cache_ttl', 86400);

        return Cache::remember(self::CACHE_KEY, $ttl, function (): ?string {
            $response = Http::get(config('services.kick.urls.public_key'));

            if ($response->failed()) {
                Log::warning('kick.webhook.public_key_fetch_failed', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->json('data.public_key') ?? $response->body();
        });
    }
}
