export type MemberProfile = {
    id: number;
    name: string;
    email: string;
    first_name: string | null;
    last_name: string | null;
    phone: string | null;
    instagram: string | null;
    twitter: string | null;
    email_verified_at: string | null;
};

export type MemberMessageRow = {
    id: number;
    body: string;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
};

export type AdminMemberMessageRow = {
    id: number;
    body: string;
    is_read: boolean;
    read_at: string | null;
    created_at: string | null;
    user: {
        id: number;
        name: string;
        first_name: string | null;
        last_name: string | null;
        email: string;
        kick_username: string | null;
    } | null;
};

export type AdminMemberRow = {
    id: number;
    name: string;
    first_name: string | null;
    last_name: string | null;
    email: string;
    kick_username: string | null;
    phone: string | null;
    instagram: string | null;
    twitter: string | null;
    is_super_admin: boolean;
    permissions: string[];
};

export type PermissionRegistry = Record<string, Record<string, string>>;

export type PaginatedMessages<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
