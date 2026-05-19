<?php

namespace App\Support;

/**
 * The single source of truth for every permission ability string in the
 * application. Ability constants are the canonical values; both all() and
 * groups() derive from them so the two can never drift apart.
 */
final class Permissions
{
    public const CONNECTIONS_MANAGE = 'connections.manage';

    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';

    public const DASHBOARD_VIEW = 'dashboard.view';

    public const MESSAGES_VIEW = 'messages.view';

    public const EVENTS_VIEW = 'events.view';

    public const COMMANDS_MANAGE = 'commands.manage';

    public const TIMERS_MANAGE = 'timers.manage';

    public const COMMAND_LOGS_VIEW = 'command-logs.view';

    public const KICK_USERS_VIEW = 'kick-users.view';

    public const KICK_USERS_MODERATE = 'kick-users.moderate';

    public const MODERATION_VIEW = 'moderation.view';

    public const MODERATION_ACT = 'moderation.act';

    public const WIKI_VIEW = 'wiki.view';

    public const WIKI_CREATE = 'wiki.create';

    public const WIKI_EDIT = 'wiki.edit';

    public const WIKI_DELETE = 'wiki.delete';

    public const MEMBER_MESSAGES_VIEW = 'member-messages.view';

    public const USERS_MANAGE = 'users.manage';

    /**
     * The grouped map for the admin UI. Keyed by Turkish group label, each
     * value maps an ability string to its Turkish item label.
     *
     * @return array<string, array<string, string>>
     */
    public static function groups(): array
    {
        return [
            'Bağlantılar' => [
                self::CONNECTIONS_MANAGE => 'Bağlantıları yönet',
            ],
            'Abonelikler' => [
                self::SUBSCRIPTIONS_MANAGE => 'Webhook aboneliklerini yönet',
            ],
            'Panel' => [
                self::DASHBOARD_VIEW => 'Panoyu görüntüle',
                self::MESSAGES_VIEW => 'Sohbet mesajlarını görüntüle',
                self::EVENTS_VIEW => 'Etkinlikleri görüntüle',
            ],
            'Bot' => [
                self::COMMANDS_MANAGE => 'Komutları yönet',
                self::TIMERS_MANAGE => 'Zamanlayıcıları yönet',
                self::COMMAND_LOGS_VIEW => 'Komut kayıtlarını görüntüle',
            ],
            'Kick Kullanıcıları' => [
                self::KICK_USERS_VIEW => 'Kick kullanıcı kaydını görüntüle',
                self::KICK_USERS_MODERATE => 'Kick kullanıcılarını moderasyon',
            ],
            'Moderasyon' => [
                self::MODERATION_VIEW => 'Moderasyonu görüntüle',
                self::MODERATION_ACT => 'Moderasyon işlemi yap',
            ],
            'Wiki' => [
                self::WIKI_VIEW => 'Wiki\'yi görüntüle',
                self::WIKI_CREATE => 'Wiki kaydı ekle',
                self::WIKI_EDIT => 'Wiki kaydı düzenle',
                self::WIKI_DELETE => 'Wiki kaydı sil',
            ],
            'Üye Mesajları' => [
                self::MEMBER_MESSAGES_VIEW => 'Üye mesajlarını görüntüle',
            ],
            'Üye Yönetimi' => [
                self::USERS_MANAGE => 'Üyeleri ve izinleri yönet',
            ],
        ];
    }

    /**
     * A flat list of every ability string, derived from groups() so the two
     * representations stay in sync.
     *
     * @return array<int, string>
     */
    public static function all(): array
    {
        $abilities = [];

        foreach (self::groups() as $items) {
            foreach (array_keys($items) as $ability) {
                $abilities[] = $ability;
            }
        }

        return $abilities;
    }

    /**
     * Whether the given ability string is a known permission.
     */
    public static function isValid(string $ability): bool
    {
        return in_array($ability, self::all(), true);
    }
}
