<?php

namespace App\Console\Commands\Kick;

use App\Models\KickConnection;
use App\Services\Kick\KickTokenManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('kick:refresh-tokens')]
#[Description('Refresh Kick OAuth tokens that are close to expiring')]
class RefreshTokensCommand extends Command
{
    public function handle(KickTokenManager $tokens): int
    {
        $connections = KickConnection::query()
            ->where('expires_at', '<=', now()->addMinutes(10))
            ->get();

        foreach ($connections as $connection) {
            try {
                $tokens->refresh($connection, force: true);
                $this->info("Refreshed [{$connection->type}] token.");
            } catch (Throwable $e) {
                report($e);
                $this->error("Failed to refresh [{$connection->type}]: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
