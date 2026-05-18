export type KickConnectionView = {
    type: 'channel' | 'bot';
    connected: boolean;
    slug: string | null;
    display_name: string | null;
    kick_user_id: number | null;
    broadcaster_user_id: number | null;
    connected_at: string | null;
    expires_at: string | null;
    is_expired: boolean;
    required_scopes: string[];
    granted_scopes: string[];
    missing_scopes: string[];
};

export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

export type ChatMessageRow = {
    id: number;
    kick_message_id: string;
    sender_username: string;
    content: string;
    is_command: boolean;
    sent_at: string | null;
    deleted_at: string | null;
};

export type FeedItem = {
    type: string;
    actor: string;
    detail: string | null;
    at: string | null;
};

export type KickUserRow = {
    id: number;
    kick_user_id: number | null;
    username: string;
    first_seen_at: string | null;
    last_seen_at: string | null;
};

export type BanStatus = {
    status: 'clean' | 'banned' | 'timed_out';
    expires_at: string | null;
};

export type KickUserDetail = {
    id: number;
    kick_user_id: number | null;
    username: string;
    identity: Record<string, unknown> | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
    ban_status: BanStatus;
    former_usernames: string[];
};

export type ChatMessageDetailRow = {
    id: number;
    kick_message_id: string;
    sender_username: string;
    content: string;
    sent_at: string | null;
    deleted_at: string | null;
};

export type UserEventItem = {
    type: 'follow' | 'subscription' | 'gift_sent' | 'redemption' | 'ban' | 'rename';
    at: string | null;
    // subscription
    sub_type?: string;
    tier?: string | null;
    duration?: number | null;
    gifter_username?: string | null;
    // gift_sent
    gift_name?: string | null;
    kicks_amount?: number;
    recipient_username?: string | null;
    message?: string | null;
    // redemption
    reward_title?: string | null;
    reward_cost?: number | null;
    status?: string | null;
    user_input?: string | null;
    // ban
    action?: string;
    reason?: string | null;
    moderator_username?: string | null;
    source?: string;
    expires_at?: string | null;
    // rename
    previous_username?: string;
    new_username?: string;
};

export type UserEvents = {
    items: UserEventItem[];
    truncated: boolean;
};

export type WikiAliasRow = {
    id: number;
    alias: string;
};

export type WikiEntryRow = {
    id: number;
    type: 'killer' | 'survivor' | 'perk' | 'power' | 'addon' | 'term';
    name_en: string;
    name_tr: string | null;
    owner: string | null;
    role: 'survivor' | 'killer' | null;
    description_tr: string | null;
    description_en: string | null;
    source_url: string | null;
    is_enabled: boolean;
    is_curated: boolean;
    aliases: WikiAliasRow[];
};
