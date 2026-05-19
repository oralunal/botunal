<?php

namespace App\Services\Kick;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Fetches the authorizing user's Kick identity using a bare access token,
 * without persisting a KickConnection or token. Used by the member
 * "Login with Kick" flow where no Kick credentials are stored.
 */
final class KickIdentity
{
    /**
     * Resolve the current Kick user for the given access token.
     *
     * @return array{user_id: int|null, name: string|null, email: string|null}
     */
    public function fetch(string $accessToken): array
    {
        $url = rtrim((string) config('services.kick.urls.api_base'), '/').'/users';

        $response = Http::withToken($accessToken)->acceptJson()->get($url);

        if ($response->failed()) {
            throw new RuntimeException('Failed to fetch Kick identity.');
        }

        $data = $response->json('data.0', []);

        return [
            'user_id' => $data['user_id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
        ];
    }
}
