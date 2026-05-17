<?php

namespace App\Console\Commands\Kick;

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Timer;
use App\Services\Kick\CommandTemplateRenderer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('kick:run-timers')]
#[Description('Post scheduled timer messages to chat when due')]
class RunTimersCommand extends Command
{
    public function handle(CommandTemplateRenderer $renderer): int
    {
        $isLive = (bool) Cache::get('kick:livestream:is_live', false);

        Timer::query()->enabled()->get()->each(function (Timer $timer) use ($isLive, $renderer): void {
            if ($timer->only_when_live && ! $isLive) {
                return;
            }

            if (! $timer->isDue()) {
                return;
            }

            if (! $this->chatActiveEnough($timer)) {
                return;
            }

            Cache::lock('kick:timer:'.$timer->id, 30)->get(function () use ($timer, $renderer): void {
                $message = $renderer->renderStandalone($timer->message);

                if ($message !== '') {
                    SendChatMessageJob::dispatch($message);
                }

                $timer->forceFill(['last_run_at' => now()])->save();
            });
        });

        return self::SUCCESS;
    }

    /**
     * Optionally require a minimum number of chat messages since the last run.
     */
    private function chatActiveEnough(Timer $timer): bool
    {
        if ($timer->min_messages_between <= 0) {
            return true;
        }

        $since = $timer->last_run_at ?? now()->subDay();

        return ChatMessage::where('sent_at', '>=', $since)->count() >= $timer->min_messages_between;
    }
}
