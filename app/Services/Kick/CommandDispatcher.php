<?php

namespace App\Services\Kick;

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Command;
use App\Models\CommandLog;
use App\Models\KickConnection;
use Illuminate\Support\Facades\Cache;

/**
 * Matches an incoming chat message to a command, enforces permission and
 * cooldown rules, renders the response and queues it back to chat.
 */
class CommandDispatcher
{
    public function __construct(
        private readonly CommandPermissionResolver $permissions,
        private readonly CommandTemplateRenderer $renderer,
        private readonly BuiltInCommandRegistry $builtIns,
    ) {}

    public function handle(ChatMessage $message): void
    {
        if ($this->isFromBot($message)) {
            return;
        }

        $prefix = (string) config('services.kick.command_prefix', '!');
        $content = trim($message->content);

        if (! str_starts_with($content, $prefix)) {
            return;
        }

        $parts = preg_split('/\s+/', mb_substr($content, mb_strlen($prefix))) ?: [];
        $trigger = strtolower((string) array_shift($parts));

        if ($trigger === '') {
            return;
        }

        $command = $this->resolve($trigger);

        if ($command === null) {
            return;
        }

        $context = new CommandContext($message, $command, $trigger, array_values($parts));

        if (! $this->permissions->allows($message, $command)) {
            $this->log($message, $command, $trigger, CommandLog::OUTCOME_DENIED);

            return;
        }

        if ($this->onCooldown($command, $message)) {
            $this->log($message, $command, $trigger, CommandLog::OUTCOME_COOLDOWN);

            return;
        }

        $response = $this->buildResponse($context);

        if ($response === '') {
            return;
        }

        $command->forceFill([
            'usage_count' => $command->usage_count + 1,
            'last_used_at' => now(),
        ])->save();

        $this->log($message, $command, $trigger, CommandLog::OUTCOME_SENT, $response);

        SendChatMessageJob::dispatch(
            $response,
            replyTo: $command->reply_in_thread ? $message->kick_message_id : null,
        )->afterCommit();
    }

    private function resolve(string $trigger): ?Command
    {
        return Command::query()
            ->enabled()
            ->where(function ($query) use ($trigger): void {
                $query->where('name', $trigger)
                    ->orWhereHas('aliases', fn ($alias) => $alias->where('alias', $trigger));
            })
            ->with('aliases')
            ->first();
    }

    private function buildResponse(CommandContext $context): string
    {
        $command = $context->command;

        if ($command->type === Command::TYPE_DYNAMIC) {
            $builtIn = $this->builtIns->resolve((string) $command->handler);

            return $builtIn === null ? '' : trim($builtIn->handle($context));
        }

        return $this->renderer->render((string) $command->response, $context);
    }

    private function onCooldown(Command $command, ChatMessage $message): bool
    {
        $global = "kick:cmd:cd:{$command->id}";
        $perUser = "kick:cmd:cd:{$command->id}:{$message->sender_kick_user_id}";

        if ($command->cooldown_seconds > 0 && ! Cache::add($global, true, $command->cooldown_seconds)) {
            return true;
        }

        if ($command->user_cooldown_seconds > 0 && ! Cache::add($perUser, true, $command->user_cooldown_seconds)) {
            return true;
        }

        return false;
    }

    private function isFromBot(ChatMessage $message): bool
    {
        $botUserId = KickConnection::bot()?->kick_user_id;

        return $botUserId !== null
            && (int) $message->sender_kick_user_id === (int) $botUserId;
    }

    private function log(ChatMessage $message, ?Command $command, string $trigger, string $outcome, ?string $response = null): void
    {
        CommandLog::create([
            'command_id' => $command?->id,
            'alias_used' => $command !== null && $command->name !== $trigger ? $trigger : null,
            'invoker_username' => $message->sender_username,
            'invoker_kick_user_id' => $message->sender_kick_user_id,
            'raw_message' => $message->content,
            'response_sent' => $response,
            'outcome' => $outcome,
            'occurred_at' => now(),
        ]);
    }
}
