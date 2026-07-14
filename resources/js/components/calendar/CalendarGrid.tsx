import { useState, useMemo, useCallback } from 'react';
import { ChevronLeft, ChevronRight, CalendarDays } from 'lucide-react';
import { EmptyState } from '@/components/ui/EmptyState';
import { cn } from '@/lib/utils';

/* ── Types ── */

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

/* ── Component ── */

export function CalendarGrid({ assignments, onDateClick, className }: CalendarGridProps) {
    const today = new Date();
    const [year, setYear] = useState(today.getFullYear());
    const [month, setMonth] = useState(today.getMonth());

    // Build a Set of date strings with events
    const markedDates = useMemo(() => {
        const set = new Set<string>();
        for (const a of assignments) {
            // Normalize "DD/MM/YYYY" or "YYYY-MM-DD" to "YYYY-MM-DD"
            let normalized = a.date;
            if (/^\d{2}\/\d{2}\/\d{4}$/.test(a.date)) {
                const [d, m, y] = a.date.split('/');
                normalized = `${y}-${m}-${d}`;
            }
            set.add(normalized);
        }
        return set;
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

    const hasEvents = assignments.length > 0;

    return (
        <div className={cn('rounded-xl border border-[#e5e5e5] bg-white p-5 shadow-[0_1px_2px_rgba(28,25,23,0.05)]', className)}>
            {/* Header */}
            <div className="mb-4 flex items-center justify-between">
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
                <EmptyState
                    icon={CalendarDays}
                    title="Sin eventos programados"
                    description="No hay evaluaciones agendadas para este período."
                />
            ) : (
                <>
                    {/* Weekday header */}
                    <div className="mb-1 grid grid-cols-7 gap-px">
                        {WEEKDAYS.map((wd) => (
                            <div
                                key={wd}
                                className="py-1.5 text-center text-[11px] font-bold uppercase tracking-[0.05em] text-[#78716c]"
                            >
                                {wd}
                            </div>
                        ))}
                    </div>

                    {/* Grid */}
                    <div className="grid grid-cols-7 gap-px">
                        {grid.map((day, idx) => {
                            if (day === null) {
                                return <div key={`blank-${idx}`} className="min-h-[44px]" />;
                            }

                            const dateKey = toDateKey(year, month, day);
                            const isMarked = markedDates.has(dateKey);
                            const isToday = dateKey === toDateKey(today.getFullYear(), today.getMonth(), today.getDate());

                            return (
                                <button
                                    key={dateKey}
                                    onClick={() => onDateClick?.(new Date(year, month, day))}
                                    disabled={!onDateClick}
                                    className={cn(
                                        'relative flex min-h-[44px] flex-col items-center justify-center rounded-lg text-sm transition-colors',
                                        isToday && 'font-bold',
                                        isMarked
                                            ? 'bg-[#fed7aa] text-[#c2410c] hover:bg-[#feb58a]'
                                            : 'text-[#1c1917] hover:bg-[#f5f5f4]',
                                        !onDateClick && 'cursor-default',
                                    )}
                                    aria-label={`${day} de ${MONTHS[month]}${isMarked ? ' — Evaluación agendada' : ''}`}
                                >
                                    <span>{day}</span>
                                    {isMarked && (
                                        <span className="mt-0.5 h-1.5 w-1.5 rounded-full bg-[#c2410c]" />
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </>
            )}
        </div>
    );
}
