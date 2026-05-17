<?php

namespace App\Jobs\Kick;

use App\Models\KickWebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Base for jobs that project a stored webhook event into a domain table.
 *
 * Idempotency: the controller records each delivery once (unique message_id),
 * and projection + markProcessed run in one transaction, so a retried job
 * never double-writes.
 */
abstract class ProcessKickEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15, 30];

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = KickWebhookEvent::find($this->webhookEventId);

        if ($event === null || $event->processed_at !== null) {
            return;
        }

        DB::transaction(function () use ($event): void {
            $this->project($event->payload, $event);
            $event->markProcessed();
        });
    }

    /**
     * Record the failure reason after the final attempt.
     */
    public function failed(Throwable $exception): void
    {
        KickWebhookEvent::find($this->webhookEventId)?->markFailed($exception->getMessage());
    }

    /**
     * Project the decoded payload into the relevant domain table.
     *
     * @param  array<string, mixed>  $payload
     */
    abstract protected function project(array $payload, KickWebhookEvent $event): void;
}
