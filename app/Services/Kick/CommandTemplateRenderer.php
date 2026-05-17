<?php

namespace App\Services\Kick;

use App\Models\CommandCounter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Expands placeholders in a static command/timer template.
 *
 * Supported tokens: {user} {channel} {args} {touser} {1}..{n}
 * {count} {uptime} {random.MIN-MAX}. Unknown tokens are stripped.
 */
class CommandTemplateRenderer
{
    public function render(string $template, CommandContext $context): string
    {
        $replacements = [
            '{user}' => $context->username(),
            '{channel}' => $context->channelSlug(),
            '{args}' => $context->argString(),
            '{touser}' => $context->args[0] ?? $context->username(),
            '{count}' => (string) CommandCounter::bump('command:'.$context->command->name),
            '{uptime}' => $this->uptime(),
        ];

        foreach ($context->args as $index => $arg) {
            $replacements['{'.($index + 1).'}'] = $arg;
        }

        $output = strtr($template, $replacements);

        $output = preg_replace_callback(
            '/\{random\.(\d+)-(\d+)\}/',
            fn (array $m): string => (string) random_int((int) $m[1], (int) $m[2]),
            $output,
        ) ?? $output;

        // Strip any remaining unknown {tokens}.
        return trim((string) preg_replace('/\{[^}]+\}/', '', $output));
    }

    /**
     * Render a template that has no command/message context (e.g. timers).
     * Only {channel}, {uptime} and {random.MIN-MAX} are meaningful here.
     */
    public function renderStandalone(string $template): string
    {
        $output = strtr($template, [
            '{channel}' => (string) config('services.kick.channel_slug'),
            '{uptime}' => $this->uptime(),
        ]);

        $output = preg_replace_callback(
            '/\{random\.(\d+)-(\d+)\}/',
            fn (array $m): string => (string) random_int((int) $m[1], (int) $m[2]),
            $output,
        ) ?? $output;

        return trim((string) preg_replace('/\{[^}]+\}/', '', $output));
    }

    /**
     * Human-readable stream uptime derived from the cached live state.
     */
    private function uptime(): string
    {
        $startedAt = Cache::get('kick:livestream:started_at');

        if (! Cache::get('kick:livestream:is_live', false) || $startedAt === null) {
            return 'offline';
        }

        return now()->diffForHumans(Carbon::parse($startedAt), true);
    }
}
