<?php

namespace App\Jobs\Kick;

use App\Models\ChatMessage;
use App\Models\KickWebhookEvent;
use App\Services\Kick\CommandDispatcher;
use Illuminate\Support\Carbon;

class ProcessChatMessageEvent extends ProcessKickEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function project(array $payload, KickWebhookEvent $event): void
    {
        $prefix = config('services.kick.command_prefix', '!');
        $content = (string) data_get($payload, 'content', '');

        $message = ChatMessage::updateOrCreate(
            ['kick_message_id' => data_get($payload, 'message_id')],
            [
                'sender_kick_user_id' => data_get($payload, 'sender.user_id'),
                'sender_username' => data_get($payload, 'sender.username', 'unknown'),
                'sender_identity' => data_get($payload, 'sender.identity'),
                'content' => $content,
                'is_command' => str_starts_with(trim($content), $prefix),
                'replied_to_message_id' => data_get($payload, 'replies_to.message_id'),
                'sent_at' => $this->timestamp($payload),
            ],
        );

        if ($message->is_command) {
            app(CommandDispatcher::class)->handle($message);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function timestamp(array $payload): Carbon
    {
        return rescue(
            fn () => Carbon::parse(data_get($payload, 'created_at')),
            now(),
            false,
        );
    }
}
