<?php

namespace App\Console\Commands\Kick;

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\KickConnection;
use App\Models\Timer;
use App\Services\Kick\CommandTemplateRenderer;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

#[Signature('kick:run-timers')]
#[Description('Post the next timer message in the rotation to chat when due')]
class RunTimersCommand extends Command
{
    public function handle(CommandTemplateRenderer $renderer): int
    {
        Cache::lock('kick:timers', 30)->get(function () use ($renderer): void {
            $this->runNextTimer($renderer);
        });

        return self::SUCCESS;
    }

    /**
     * Fire the timer that follows the last one to run, once it has waited its
     * interval and viewers have chatted enough since the previous timer message.
     */
    private function runNextTimer(CommandTemplateRenderer $renderer): void
    {
        $isLive = (bool) Cache::get('kick:livestream:is_live', false);

        $timers = Timer::query()->enabled()->inRotationOrder()->get()
            ->reject(fn (Timer $timer): bool => $timer->only_when_live && ! $isLive);

        if ($timers->isEmpty()) {
            return;
        }

        $lastRun = Timer::query()->whereNotNull('last_run_at')->latest('last_run_at')->first();
        $streamStartedAt = $isLive ? $this->streamStartedAt() : null;

        if ($streamStartedAt !== null && ($lastRun === null || $lastRun->last_run_at->lessThan($streamStartedAt))) {
            $lastRun = null;
        }

        $next = $timers->first(fn (Timer $timer): bool => $lastRun === null || $timer->comesAfter($lastRun)) ?? $timers->first();
        $previousRunAt = $lastRun?->last_run_at ?? $streamStartedAt ?? $next->created_at;

        if (! $next->isDueAfter($previousRunAt) || ! $this->chatActiveEnough($next, $previousRunAt)) {
            return;
        }

        $message = $renderer->renderStandalone($next->message);

        if ($message !== '') {
            SendChatMessageJob::dispatch($message);
        }

        $next->forceFill(['last_run_at' => now()])->save();
    }

    /**
     * Optionally require a minimum number of viewer messages since the previous timer message.
     */
    private function chatActiveEnough(Timer $timer, CarbonInterface $since): bool
    {
        if ($timer->min_messages_between <= 0) {
            return true;
        }

        $botUserId = KickConnection::bot()?->kick_user_id;

        $viewerMessages = ChatMessage::query()
            ->where('sent_at', '>=', $since)
            ->when($botUserId !== null, fn (Builder $query) => $query->where('sender_kick_user_id', '!=', $botUserId))
            ->count();

        return $viewerMessages >= $timer->min_messages_between;
    }

    /**
     * When the current stream went live; a new stream restarts the rotation from the first timer.
     */
    private function streamStartedAt(): ?CarbonInterface
    {
        $startedAt = Cache::get('kick:livestream:started_at');

        return $startedAt === null ? null : rescue(fn () => Date::parse($startedAt), null, false);
    }
}
