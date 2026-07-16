import { useState, useMemo, useCallback } from 'react';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { EmptyState } from '@/components/ui/EmptyState';
import { cn } from '@/lib/utils';

/* ── Types ── */

const MAX_VISIBLE_EVENTS = 3;

export interface CalendarAssignment {
    date: string; // "YYYY-MM-DD"
    label?: string;
}

export interface CalendarGridProps {
    assignments: CalendarAssignment[];
    onDateClick?: (date: Date) => void;
    className?: string;
}

/* ── Helpers ── */

const WEEKDAYS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const MONTHS = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function getMonthGrid(year: number, month: number): (number | null)[] {
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const grid: (number | null)[] = [];

    // leading blanks
    for (let i = 0; i < firstDay; i++) grid.push(null);

    // actual days
    for (let d = 1; d <= daysInMonth; d++) grid.push(d);

    // trailing blanks to fill the last row
    while (grid.length % 7 !== 0) grid.push(null);

    return grid;
}

function toDateKey(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function normalizeDate(dateStr: string): string {
    if (/^\d{2}\/\d{2}\/\d{4}$/.test(dateStr)) {
        const [d, m, y] = dateStr.split('/');
        return `${y}-${m}-${d}`;
    }
    return dateStr;
}

/* ── Component ── */

export function CalendarGrid({ assignments, onDateClick, className }: CalendarGridProps) {
    const today = new Date();
    const [year, setYear] = useState(today.getFullYear());
    const [month, setMonth] = useState(today.getMonth());
    const [hoveredDate, setHoveredDate] = useState<string | null>(null);

    // Build a date → labels map for events
    const eventsByDate = useMemo(() => {
        const map = new Map<string, string[]>();
        for (const a of assignments) {
            const key = normalizeDate(a.date);
            if (!map.has(key)) map.set(key, []);
            const labels = map.get(key)!;
            if (a.label && labels.length < 10) labels.push(a.label);
        }
        return map;
    }, [assignments]);

    const grid = useMemo(() => getMonthGrid(year, month), [year, month]);

    const prevMonth = useCallback(() => {
        if (month === 0) {
            setYear((y) => y - 1);
            setMonth(11);
        } else {
            setMonth((m) => m - 1);
        }
    }, [month]);

    const nextMonth = useCallback(() => {
        if (month === 11) {
            setYear((y) => y + 1);
            setMonth(0);
        } else {
            setMonth((m) => m + 1);
        }
    }, [month]);

    const todayKey = toDateKey(today.getFullYear(), today.getMonth(), today.getDate());
    const hasEvents = assignments.length > 0;

    return (
        <div className={cn('rounded-xl border border-[#e5e5e5] bg-white shadow-[0_1px_2px_rgba(28,25,23,0.05)]', className)}>
            {/* Header */}
            <div className="flex items-center justify-between border-b border-[#e5e5e5] px-5 py-3">
                <div className="flex items-center gap-2">
                    <CalendarDays className="h-5 w-5 text-[#c2410c]" />
                    <h3 className="text-base font-bold text-[#1c1917]">
                        {MONTHS[month]} {year}
                    </h3>
                </div>
                <div className="flex items-center gap-1">
                    <button
                        onClick={prevMonth}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                        aria-label="Mes anterior"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <button
                        onClick={nextMonth}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4]"
                        aria-label="Mes siguiente"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>
            </div>

            {!hasEvents ? (
                <div className="p-10">
                    <EmptyState
                        icon={CalendarDays}
                        title="Sin eventos programados"
                        description="No hay evaluaciones agendadas para este período."
                    />
                </div>
            ) : (
                <>
                    {/* Weekday header */}
                    <div className="grid grid-cols-7 border-b border-[#e5e5e5]">
                        {WEEKDAYS.map((wd) => (
                            <div
                                key={wd}
                                className="border-r border-[#e5e5e5] py-2 text-center text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c] last:border-r-0"
                            >
                                {wd}
                            </div>
                        ))}
                    </div>

                    {/* Grid */}
                    <div className="grid grid-cols-7">
                        {grid.map((day, idx) => {
                            if (day === null) {
                                return (
                                    <div
                                        key={`blank-${idx}`}
                                        className="min-h-[88px] border-b border-r border-[#e5e5e5] bg-[#fafaf9] last:border-r-0"
                                    />
                                );
                            }

                            const dateKey = toDateKey(year, month, day);
                            const dayEvents = eventsByDate.get(dateKey) ?? [];
                            const isToday = dateKey === todayKey;
                            const isHovered = hoveredDate === dateKey;
                            const visibleEvents = dayEvents.slice(0, MAX_VISIBLE_EVENTS);
                            const moreCount = dayEvents.length - MAX_VISIBLE_EVENTS;

                            return (
                                <div
                                    key={dateKey}
                                    onMouseEnter={() => setHoveredDate(dateKey)}
                                    onMouseLeave={() => setHoveredDate(null)}
                                    onClick={() => onDateClick?.(new Date(year, month, day))}
                                    className={cn(
                                        'relative flex min-h-[88px] cursor-default flex-col border-b border-r border-[#e5e5e5] p-1.5 transition-colors last:border-r-0',
                                        isToday && 'bg-[#fff7ed]',
                                        !isToday && 'bg-white',
                                        onDateClick && 'cursor-pointer hover:bg-[#f5f5f4]',
                                    )}
                                    aria-label={`${day} de ${MONTHS[month]}${dayEvents.length > 0 ? ` — ${dayEvents.length} evaluación(es) agendada(s)` : ''}`}
                                >
                                    {/* Day number */}
                                    <div className="mb-1 flex items-center justify-center">
                                        <span
                                            className={cn(
                                                'inline-flex h-7 w-7 items-center justify-center text-sm font-medium leading-none',
                                                isToday
                                                    ? 'rounded-full bg-[#c2410c] text-white'
                                                    : 'text-[#1c1917]',
                                            )}
                                        >
                                            {day}
                                        </span>
                                    </div>

                                    {/* Event chips */}
                                    <div className="flex flex-col gap-0.5 overflow-hidden">
                                        {visibleEvents.map((label, ei) => (
                                            <div
                                                key={ei}
                                                className="truncate rounded bg-[#fed7aa] px-1.5 py-0.5 text-[10px] font-medium leading-tight text-[#9a330a]"
                                                title={label}
                                            >
                                                {label}
                                            </div>
                                        ))}
                                        {moreCount > 0 && (
                                            <span className="px-1 text-[10px] font-semibold text-[#78716c]">
                                                +{moreCount} más
                                            </span>
                                        )}
                                    </div>

                                    {/* Hover tooltip */}
                                    {isHovered && dayEvents.length > 0 && (
                                        <div className="absolute left-0 top-full z-10 mt-1 w-56 rounded-lg border border-[#e5e5e5] bg-white p-2 shadow-[0_8px_24px_rgba(28,25,23,0.12)]">
                                            <p className="mb-1.5 text-[11px] font-bold uppercase tracking-wider text-[#78716c]">
                                                {dayEvents.length} evaluación(es)
                                            </p>
                                            {dayEvents.map((label, ei) => (
                                                <p key={ei} className="truncate py-0.5 text-xs text-[#1c1917]">
                                                    {label}
                                                </p>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}
