<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickWebhookEvent;
use App\Services\Kick\KickEventMap;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class WebhookController extends Controller
{
    /**
     * Ingest a signed Kick webhook. The signature is already verified by the
     * kick.signature middleware. Work is idempotent and queued; we return 200
     * fast so Kick does not retry / unsubscribe us.
     */
    public function __invoke(Request $request): Response
    {
        $messageId = $request->header('Kick-Event-Message-Id');
        $eventType = $request->header('Kick-Event-Type', '');

        $event = KickWebhookEvent::firstOrCreate(
            ['message_id' => $messageId],
            [
                'event_type' => $eventType,
                'event_version' => (int) $request->header('Kick-Event-Version', 1),
                'kick_timestamp' => $this->parseTimestamp($request->header('Kick-Event-Message-Timestamp')),
                'payload' => $request->json()->all(),
                'headers' => [
                    'event_type' => $eventType,
                    'event_version' => $request->header('Kick-Event-Version'),
                ],
            ],
        );

        // Duplicate delivery: already recorded, do not reprocess.
        if (! $event->wasRecentlyCreated) {
            return response('', 200);
        }

        if ($job = KickEventMap::jobFor($eventType)) {
            $job::dispatch($event->id);
        } else {
            $event->markProcessed();
        }

        return response('', 200);
    }

    private function parseTimestamp(?string $timestamp): ?Carbon
    {
        if ($timestamp === null) {
            return null;
        }

        return rescue(fn () => Carbon::parse($timestamp), null, false);
    }
}
