<?php

namespace App\Jobs\Kick;

use App\Services\Kick\KickApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Posts a message to Kick chat as the bot (or channel) account.
 */
class SendChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(
        public string $content,
        public ?string $sendAs = null,
        public ?string $replyTo = null,
    ) {}

    public function handle(KickApiClient $api): void
    {
        $type = $this->sendAs ?? (string) config('services.kick.send_as', 'user');

        // Respect a conservative outbound chat rate to avoid Kick throttling.
        $allowed = RateLimiter::attempt(
            'kick:chat:send',
            maxAttempts: 20,
            callback: fn () => $api->sendChatMessage($this->content, $type, replyToMessageId: $this->replyTo),
            decaySeconds: 30,
        );

        if (! $allowed) {
            $this->release(10);
        }
    }
}
