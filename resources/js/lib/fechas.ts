/**
 * Central date formatting / parsing helpers (RF-DATE-01).
 *
 * All user-facing dates MUST go through `formatFecha` (DD/MM/AAAA) or
 * `formatFechaHora` (DD/MM/AAAA HH:mm). Date-only strings (e.g. `2026-09-26`
 * from `<input type="date">` or the API) MUST be parsed with
 * `parseDateOnlyLocal` — never `new Date(iso)` — so the rendered day never
 * shifts with the browser timezone. Backend is unchanged.
 */

export type FechaInput = string | Date | null | undefined;

function pad2(n: number): string {
    return String(n).padStart(2, '0');
}

const DATE_ONLY_RE = /^(\d{4})-(\d{2})-(\d{2})/;

/**
 * Parse a date value as a LOCAL date — no UTC midnight conversion, hence no
 * TZ day-shift. Exact date-only strings (`2026-09-26`, e.g. from
 * `<input type="date">` or the API) are built with `new Date(y, m - 1, d)`.
 * Datetimes keep their time via `new Date(value)`. Returns null for empty /
 * invalid input.
 */
export function parseDateOnlyLocal(value: FechaInput): Date | null {
    if (value == null) return null;
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
    const raw = value.trim();
    if (!raw) return null;
    const m = DATE_ONLY_RE.exec(raw);
    if (m && /^\d{4}-\d{2}-\d{2}$/.test(raw)) {
        return new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    }
    const d = new Date(raw);
    return isNaN(d.getTime()) ? null : d;
}

function toDate(value: FechaInput): Date | null {
    if (value instanceof Date) return isNaN(value.getTime()) ? null : value;
    if (value == null) return null;
    if (typeof value === 'string' && !value.trim()) return null;
    return parseDateOnlyLocal(value);
}

/** Format as DD/MM/AAAA regardless of browser TZ. Invalid → original string or '—'. */
export function formatFecha(value: FechaInput): string {
    const d = toDate(value);
    if (!d) return typeof value === 'string' && value.trim() ? value.trim() : '—';
    return `${pad2(d.getDate())}/${pad2(d.getMonth() + 1)}/${d.getFullYear()}`;
}

/** Format as DD/MM/AAAA HH:mm regardless of browser TZ. Invalid → original string or '—'. */
export function formatFechaHora(value: FechaInput): string {
    const d = toDate(value);
    if (!d) return typeof value === 'string' && value.trim() ? value.trim() : '—';
    return `${formatFecha(d)} ${pad2(d.getHours())}:${pad2(d.getMinutes())}`;
}

/**
 * Format a Date as yyyy-MM-dd for `<input type="date">` using LOCAL fields
 * (no `toISOString`, which is UTC and can shift the day). Used both for the
 * default value and when loading a stored date into a form.
 */
export function toDateInputValue(date: Date = new Date()): string {
    return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
}
