<?php

use App\Jobs\Kick\SendChatMessageJob;
use App\Models\ChatMessage;
use App\Models\Command;
use App\Models\CommandLog;
use App\Models\WikiEntry;
use App\Services\Kick\BuiltInCommandRegistry;
use App\Services\Kick\BuiltIns\WikiCommand;
use App\Services\Kick\CommandContext;
use App\Services\Kick\CommandDispatcher;
use App\Services\Kick\WikiText;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.kick.command_prefix', '!');
    config()->set('services.kick.channel_slug', 'trolunal');
});

test('the registry maps the wiki handler', function () {
    expect(BuiltInCommandRegistry::handlers())->toContain('wiki')
        ->and(app(BuiltInCommandRegistry::class)->resolve('wiki'))
        ->toBeInstanceOf(WikiCommand::class);
});

test('WikiCommand returns the formatted answer for its args', function () {
    $entry = WikiEntry::factory()->perk('David King', 'survivor')->create([
        'name_en' => 'Dead Hard', 'name_tr' => 'Kum Torbası',
        'slug' => 'perk david king dead hard', 'description_tr' => 'Dayanıklılık.',
    ]);
    $entry->aliases()->create(['alias' => 'Kum Torbası', 'alias_norm' => WikiText::normalize('Kum Torbası')]);

    $command = Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);
    $message = ChatMessage::factory()->make(['sender_username' => 'bob']);
    $context = new CommandContext($message, $command, 'wiki', ['kum', 'torbası']);

    expect(app(WikiCommand::class)->handle($context))
        ->toBe('Kum Torbası (Dead Hard) — Kurban: David King · Açıklama: Dayanıklılık.');
});

test('bare !wiki returns usage', function () {
    $command = Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);
    $context = new CommandContext(ChatMessage::factory()->make(), $command, 'wiki', []);

    expect(app(WikiCommand::class)->handle($context))->toContain('Kullanım: !wiki');
});

test('end to end: !wiki dispatches through the command pipeline', function () {
    Queue::fake();
    WikiEntry::factory()->create([
        'type' => 'term', 'name_en' => 'Tunnel', 'name_tr' => 'Tünelleme',
        'slug' => 'term tunnel', 'description_tr' => 'Aynı kurbanı kovalama.',
    ]);
    Command::factory()->dynamic('wiki')->create(['name' => 'wiki']);

    $message = ChatMessage::factory()->create([
        'content' => '!wiki tunnel', 'is_command' => true, 'sender_kick_user_id' => 7,
        'sender_identity' => ['badges' => []],
    ]);
    app(CommandDispatcher::class)->handle($message);

    Queue::assertPushed(SendChatMessageJob::class, fn (SendChatMessageJob $j) => str_contains($j->content, 'Tünelleme (Tunnel)'));
    expect(CommandLog::where('outcome', 'sent')->count())->toBe(1);
});
