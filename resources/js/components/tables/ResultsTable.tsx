import type { EvaluacionResult } from '@/hooks/useEvaluaciones';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';

/* ── Types ── */

export interface ResultsTableProps {
    data: EvaluacionResult[];
    loading?: boolean;
}

/* ── Helpers ── */

function formatNota(nota: number | null): React.ReactNode {
    if (nota === null || nota === undefined) {
        return <StatusBadge variant="warning">Pendiente</StatusBadge>;
    }
    return (
        <span className="font-semibold tabular-nums text-[#1c1917]">
            {nota.toFixed(2)}
        </span>
    );
}

function formatArray(arr: string[]): string {
    return arr.length > 0 ? arr.join(', ') : '—';
}

/* ── Columns ── */

const columns: Column<EvaluacionResult>[] = [
    {
        key: 'proyecto_codigo',
        label: 'ID Proyecto',
        render: (row) => (
            <span className="font-medium text-[#1c1917]">
                {row.proyecto_codigo || `PG-${String(row.proyecto_id).padStart(4, '0')}`}
            </span>
        ),
    },
    {
        key: 'proyecto_nombre',
        label: 'Nombre Proyecto',
        render: (row) => (
            <span className="text-[#57534e]">{row.proyecto_nombre}</span>
        ),
    },
    {
        key: 'estudiantes',
        label: 'Estudiantes',
        render: (row) => (
            <span className="text-[#57534e]">{formatArray(row.estudiantes)}</span>
        ),
    },
    {
        key: 'director',
        label: 'Director',
        render: (row) => (
            <span className="text-[#57534e]">{row.director || '—'}</span>
        ),
    },
    {
        key: 'fase',
        label: 'Fase Evaluada',
        render: (row) => (
            <StatusBadge variant="info">{row.fase || '—'}</StatusBadge>
        ),
    },
    {
        key: 'evaluadores',
        label: 'Evaluadores',
        render: (row) => (
            <span className="text-[#57534e]">{formatArray(row.evaluadores)}</span>
        ),
    },
    {
        key: 'nota_promedio',
        label: 'Nota Promedio',
        className: 'text-right',
        render: (row) => formatNota(row.nota_promedio),
    },
];

/* ── Component ── */

export function ResultsTable({ data, loading = false }: ResultsTableProps) {
    return (
        <DataTable<EvaluacionResult>
            columns={columns}
            data={data}
            loading={loading}
            emptyMessage="No hay evaluaciones registradas."
            getRowKey={(row) => row.id}
        />
    );
}
