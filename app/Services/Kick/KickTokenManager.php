<?php

namespace App\Services\Kick;

use App\Models\KickConnection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Manages OAuth token lifecycle for Kick connections:
 * authorization-code exchange, refresh, and lazy validity checks.
 */
class KickTokenManager
{
    /**
     * Return a valid access token for the connection, refreshing if needed.
     */
    public function validToken(KickConnection $connection): string
    {
        if ($connection->isExpired()) {
            $connection = $this->refresh($connection);
        }

        return $connection->access_token;
    }

    /**
     * Exchange an authorization code for tokens (PKCE flow).
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int, scope: string, token_type: string}
     */
    public function exchangeCode(string $code, string $verifier): array
    {
        $response = Http::asForm()->post(config('services.kick.urls.token'), [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.kick.client_id'),
            'client_secret' => config('services.kick.client_secret'),
            'redirect_uri' => config('services.kick.redirect_uri'),
            'code' => $code,
            'code_verifier' => $verifier,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Kick token exchange failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Refresh the connection's access token. Guarded by a lock so concurrent
     * queue workers / scheduler do not race the refresh endpoint.
     *
     * The lazy path (validToken) passes $force = false so a second worker that
     * waited on the lock skips a redundant refresh. The scheduled command
     * passes $force = true to proactively refresh tokens nearing expiry.
     */
    public function refresh(KickConnection $connection, bool $force = false): KickConnection
    {
        return Cache::lock('kick:refresh:'.$connection->id, 10)->block(5, function () use ($connection, $force) {
            $connection->refresh();

            if (! $force && ! $connection->isExpired()) {
                return $connection;
            }

            $response = Http::asForm()->post(config('services.kick.urls.token'), [
                'grant_type' => 'refresh_token',
                'client_id' => config('services.kick.client_id'),
                'client_secret' => config('services.kick.client_secret'),
                'refresh_token' => $connection->refresh_token,
            ]);

            if ($response->failed()) {
                throw new RuntimeException(
                    "Kick token refresh failed for connection [{$connection->type}]: ".$response->body()
                );
            }

            $data = $response->json();

            $connection->forceFill([
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
                'scopes' => isset($data['scope']) ? explode(' ', $data['scope']) : $connection->scopes,
                'token_type' => $data['token_type'] ?? $connection->token_type,
                'expires_at' => Carbon::now()->addSeconds((int) ($data['expires_in'] ?? 3600)),
                'last_refreshed_at' => Carbon::now(),
            ])->save();

            return $connection;
        });
    }
}
