import type { ReactNode } from 'react';
import { Loader2, ChevronLeft, ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface Column<T> {
    key: string;
    label: string;
    render?: (row: T, index: number) => ReactNode;
    className?: string;
}

export interface PaginationProps {
    currentPage: number;
    lastPage: number;
    total: number;
    onPageChange: (page: number) => void;
}

export interface DataTableProps<T> {
    columns: Column<T>[];
    data: T[];
    loading?: boolean;
    emptyMessage?: string;
    pagination?: PaginationProps;
    getRowKey: (row: T, index: number) => string | number;
}

export function DataTable<T>({
    columns,
    data,
    loading = false,
    emptyMessage = 'No hay datos disponibles.',
    pagination,
    getRowKey,
}: DataTableProps<T>) {
    return (
        <div className="w-full overflow-x-auto rounded-lg border border-[#e5e5e5] bg-white">
            {loading ? (
                <div className="flex items-center justify-center py-16">
                    <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                </div>
            ) : data.length === 0 ? (
                <div className="py-16 text-center text-sm text-[#57534e]">
                    {emptyMessage}
                </div>
            ) : (
                <>
                    <table className="w-full text-left text-sm tabular-nums">
                        <thead className="bg-[#f5f5f4] text-[11px] font-bold uppercase tracking-[0.05em] text-[#57534e]">
                            <tr>
                                {columns.map((col) => (
                                    <th
                                        key={col.key}
                                        className={cn('whitespace-nowrap px-4 py-3', col.className)}
                                    >
                                        {col.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {data.map((row, idx) => (
                                <tr
                                    key={getRowKey(row, idx)}
                                    className="border-b border-[#e5e5e5] last:border-none"
                                >
                                    {columns.map((col) => (
                                        <td
                                            key={col.key}
                                            className={cn('px-4 py-3', col.className)}
                                        >
                                            {col.render
                                                ? col.render(row, idx)
                                                : (row as Record<string, unknown>)[col.key] as ReactNode}
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {pagination && pagination.lastPage > 1 && (
                        <div className="flex items-center justify-between border-t border-[#e5e5e5] px-4 py-3 text-sm text-[#57534e] flex-wrap gap-3">
                            <span>
                                Mostrando{' '}
                                {pagination.total > 0
                                    ? (pagination.currentPage - 1) * 20 + 1
                                    : 0}
                                –{Math.min(pagination.currentPage * 20, pagination.total)} de{' '}
                                {pagination.total} resultados
                            </span>
                            <div className="flex items-center gap-1">
                                <button
                                    onClick={() => pagination.onPageChange(pagination.currentPage - 1)}
                                    disabled={pagination.currentPage <= 1}
                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] text-[#1c1917] transition-colors hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Página anterior"
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                </button>
                                {Array.from({ length: pagination.lastPage }, (_, i) => i + 1).map(
                                    (p) => (
                                        <button
                                            key={p}
                                            onClick={() => pagination.onPageChange(p)}
                                            className={cn(
                                                'inline-flex h-8 min-w-[32px] items-center justify-center rounded-lg border px-2 text-sm font-semibold transition-colors',
                                                p === pagination.currentPage
                                                    ? 'border-[#c2410c] bg-[#c2410c] text-white'
                                                    : 'border-[#e5e5e5] text-[#1c1917] hover:bg-[#f5f5f4]',
                                            )}
                                            aria-current={p === pagination.currentPage ? 'page' : undefined}
                                        >
                                            {p}
                                        </button>
                                    ),
                                )}
                                <button
                                    onClick={() => pagination.onPageChange(pagination.currentPage + 1)}
                                    disabled={pagination.currentPage >= pagination.lastPage}
                                    className="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-[#e5e5e5] text-[#1c1917] transition-colors hover:bg-[#f5f5f4] disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="Página siguiente"
                                >
                                    <ChevronRight className="h-4 w-4" />
                                </button>
                            </div>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
