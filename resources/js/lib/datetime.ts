/**
 * Server timestamps are sent as UTC ISO 8601 strings (the app runs in UTC).
 * These helpers render them in Istanbul time (UTC+3, no DST since 2016) with a
 * human-friendly Turkish format.
 */

const dateTime = new Intl.DateTimeFormat('tr-TR', {
    timeZone: 'Europe/Istanbul',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
});

const dateOnly = new Intl.DateTimeFormat('tr-TR', {
    timeZone: 'Europe/Istanbul',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

function format(formatter: Intl.DateTimeFormat, iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return iso;
    }

    return formatter.format(date);
}

export function formatIstanbul(iso: string | null | undefined): string {
    return format(dateTime, iso);
}

export function formatIstanbulDate(iso: string | null | undefined): string {
    return format(dateOnly, iso);
}
