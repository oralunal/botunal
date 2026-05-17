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
};

export type FeedItem = {
    type: string;
    actor: string;
    detail: string | null;
    at: string | null;
};
