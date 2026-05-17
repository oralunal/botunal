<?php

namespace App\Http\Middleware;

use App\Services\Kick\KickSignatureVerifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifyKickSignature
{
    public function __construct(private readonly KickSignatureVerifier $verifier) {}

    /**
     * Reject inbound webhook requests that are not signed by Kick.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $messageId = $request->header('Kick-Event-Message-Id');
        $timestamp = $request->header('Kick-Event-Message-Timestamp');
        $signature = $request->header('Kick-Event-Signature');

        if ($messageId === null || $timestamp === null || $signature === null) {
            Log::warning('kick.webhook.signature_missing_headers');

            abort(403);
        }

        $valid = $this->verifier->verify(
            $messageId,
            $timestamp,
            $request->getContent(),
            $signature,
        );

        if (! $valid) {
            Log::warning('kick.webhook.signature_failed', [
                'message_id' => $messageId,
                'event_type' => $request->header('Kick-Event-Type'),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
