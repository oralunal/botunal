<?php

namespace App\Services\Kick;

use App\Models\KickConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin gateway over the Kick public API. Every outbound call flows through
 * here so token refresh, 401 retry, and base URL handling live in one place.
 */
class KickApiClient
{
    public function __construct(private readonly KickTokenManager $tokens) {}

    /**
     * Get the authorised user for a connection (no id => current user).
     *
     * @return array<string, mixed>
     */
    public function currentUser(KickConnection $connection): array
    {
        return $this->request($connection, 'get', '/users')['data'][0] ?? [];
    }

    /**
     * Look up users by their Kick ids.
     *
     * @param  array<int, int>  $ids
     * @return array<int, array<string, mixed>>
     */
    public function usersByIds(array $ids): array
    {
        $connection = $this->channelConnection();

        return $this->request($connection, 'get', '/users', [
            'query' => ['id' => $ids],
        ])['data'] ?? [];
    }

    /**
     * Resolve a channel (and its broadcaster_user_id) by slug.
     *
     * @return array<string, mixed>
     */
    public function channelBySlug(string $slug): array
    {
        $connection = $this->channelConnection();

        return $this->request($connection, 'get', '/channels', [
            'query' => ['slug' => $slug],
        ])['data'][0] ?? [];
    }

    /**
     * Send a chat message. "bot" uses the bot connection and posts to the
     * channel attached to that token; "user" posts via the channel token.
     *
     * @return array<string, mixed>
     */
    public function sendChatMessage(string $content, string $type = 'bot', ?int $broadcasterUserId = null, ?string $replyToMessageId = null): array
    {
        $content = Str::limit($content, 500, '');

        if ($type === 'bot') {
            $connection = $this->botConnection();
            $body = ['content' => $content, 'type' => 'bot'];
        } else {
            $connection = $this->channelConnection();
            $body = [
                'content' => $content,
                'type' => 'user',
                'broadcaster_user_id' => $broadcasterUserId ?? $connection->broadcaster_user_id,
            ];
        }

        if ($replyToMessageId !== null) {
            $body['reply_to_message_id'] = $replyToMessageId;
        }

        return $this->request($connection, 'post', '/chat', ['json' => $body]);
    }

    /**
     * Delete a chat message (moderation).
     *
     * @return array<string, mixed>
     */
    public function deleteChatMessage(string $messageId): array
    {
        return $this->request($this->channelConnection(), 'delete', '/chat/'.$messageId);
    }

    /**
     * List the app's webhook event subscriptions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSubscriptions(): array
    {
        return $this->request($this->channelConnection(), 'get', '/events/subscriptions')['data'] ?? [];
    }

    /**
     * Subscribe to webhook events.
     *
     * @param  array<int, array{name: string, version: int}>  $events
     * @return array<string, mixed>
     */
    public function createSubscription(array $events, ?int $broadcasterUserId = null): array
    {
        $body = ['events' => $events, 'method' => 'webhook'];

        if ($broadcasterUserId !== null) {
            $body['broadcaster_user_id'] = $broadcasterUserId;
        }

        return $this->request($this->channelConnection(), 'post', '/events/subscriptions', ['json' => $body]);
    }

    /**
     * Delete a webhook event subscription by Kick subscription id.
     *
     * @return array<string, mixed>
     */
    public function deleteSubscription(string $subscriptionId): array
    {
        return $this->request($this->channelConnection(), 'delete', '/events/subscriptions', [
            'query' => ['id' => [$subscriptionId]],
        ]);
    }

    /**
     * Ban or timeout a user. Omit $durationMinutes for a permanent ban.
     *
     * @return array<string, mixed>
     */
    public function banUser(int $broadcasterUserId, int $userId, ?int $durationMinutes = null, ?string $reason = null): array
    {
        $body = ['broadcaster_user_id' => $broadcasterUserId, 'user_id' => $userId];

        if ($durationMinutes !== null) {
            $body['duration'] = $durationMinutes;
        }

        if ($reason !== null) {
            $body['reason'] = Str::limit($reason, 100, '');
        }

        return $this->request($this->channelConnection(), 'post', '/moderation/bans', ['json' => $body]);
    }

    /**
     * Remove a ban or timeout from a user.
     *
     * @return array<string, mixed>
     */
    public function unbanUser(int $broadcasterUserId, int $userId): array
    {
        return $this->request($this->channelConnection(), 'delete', '/moderation/bans', [
            'json' => ['broadcaster_user_id' => $broadcasterUserId, 'user_id' => $userId],
        ]);
    }

    /**
     * Escape hatch for any future endpoint not yet wrapped above.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function raw(string $method, string $path, array $options = [], string $as = KickConnection::TYPE_CHANNEL): array
    {
        $connection = $as === KickConnection::TYPE_BOT
            ? $this->botConnection()
            : $this->channelConnection();

        return $this->request($connection, $method, $path, $options);
    }

    /**
     * Perform an authenticated request, refreshing the token once on a 401.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function request(KickConnection $connection, string $method, string $path, array $options = []): array
    {
        $response = $this->client($connection, $options)->{$method}($this->url($path), $this->payload($method, $options));

        if ($response->status() === 401) {
            $this->tokens->refresh($connection);
            $response = $this->client($connection->fresh(), $options)->{$method}($this->url($path), $this->payload($method, $options));
        }

        if ($response->failed()) {
            throw new RuntimeException(
                "Kick API {$method} {$path} failed [{$response->status()}]: ".$response->body()
            );
        }

        return $response->json() ?? [];
    }

    /**
     * Build a configured HTTP client for a connection.
     *
     * @param  array<string, mixed>  $options
     */
    private function client(KickConnection $connection, array $options): PendingRequest
    {
        $request = Http::withToken($this->tokens->validToken($connection))
            ->acceptJson()
            ->retry(2, 200, throw: false);

        if (isset($options['query'])) {
            $request = $request->withQueryParameters($options['query']);
        }

        return $request;
    }

    /**
     * Resolve the request body for write verbs.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function payload(string $method, array $options): array
    {
        if (in_array($method, ['get', 'delete'], true) && ! isset($options['json'])) {
            return [];
        }

        return $options['json'] ?? [];
    }

    private function url(string $path): string
    {
        return rtrim(config('services.kick.urls.api_base'), '/').$path;
    }

    private function channelConnection(): KickConnection
    {
        return KickConnection::channel()
            ?? throw new RuntimeException('Kick channel connection is not configured.');
    }

    private function botConnection(): KickConnection
    {
        return KickConnection::bot()
            ?? throw new RuntimeException('Kick bot connection is not configured.');
    }
}
