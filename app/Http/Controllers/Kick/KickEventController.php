<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Models\KickBan;
use App\Models\KickFollow;
use App\Models\KickGift;
use App\Models\KickSubscription;
use App\Models\LivestreamEvent;
use App\Models\RewardRedemption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KickEventController extends Controller
{
    /** @var array<string, array{0: class-string<Model>, 1: string}> */
    private const array TYPES = [
        'follows' => [KickFollow::class, 'followed_at'],
        'subscriptions' => [KickSubscription::class, 'occurred_at'],
        'gifts' => [KickGift::class, 'occurred_at'],
        'redemptions' => [RewardRedemption::class, 'redeemed_at'],
        'bans' => [KickBan::class, 'occurred_at'],
        'livestream' => [LivestreamEvent::class, 'occurred_at'],
    ];

    /**
     * Paginated event log, switched by a `type` query parameter.
     */
    public function index(Request $request): Response
    {
        $type = $request->string('type')->toString();

        if (! array_key_exists($type, self::TYPES)) {
            $type = 'follows';
        }

        /** @var class-string<Model> $model */
        [$model, $dateColumn] = self::TYPES[$type];

        return Inertia::render('kick/Events', [
            'type' => $type,
            'types' => array_keys(self::TYPES),
            'events' => $model::query()->latest($dateColumn)->paginate(50)->withQueryString(),
        ]);
    }
}
