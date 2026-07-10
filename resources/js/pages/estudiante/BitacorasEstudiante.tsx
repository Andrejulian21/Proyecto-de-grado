import { useState } from 'react';
import { Plus, Eye, Pencil, FileText, Loader2 } from 'lucide-react';
import { PageHeader } from '@/components/ui/PageHeader';
import { DataTable, type Column } from '@/components/ui/DataTable';
import { StatusBadge } from '@/components/ui/StatusBadge';
import { EmptyState } from '@/components/ui/EmptyState';

/* ── Types ── */

interface Binnacle {
    id: number;
    date: string;
    topic: string;
    description: string;
    duration: string;
    signatureStatus: 'signed' | 'pending' | 'unsigned';
    projectName: string;
}

/* ── Mock data ── */

const MOCK_BINNACLES: Binnacle[] = [
    { id: 1, date: '10/03/2026', topic: 'Definición del alcance del proyecto', description: 'Se definieron los límites del sistema y los módulos principales a desarrollar.', duration: '1h 30m', signatureStatus: 'signed', projectName: 'PG-2026-014' },
    { id: 2, date: '17/03/2026', topic: 'Revisión del cronograma de actividades', description: 'Se ajustaron las fechas de entregas parciales según el calendario académico.', duration: '1h', signatureStatus: 'signed', projectName: 'PG-2026-014' },
    { id: 3, date: '24/03/2026', topic: 'Análisis de requisitos funcionales', description: 'Se listaron y priorizaron los requisitos funcionales del sistema.', duration: '2h', signatureStatus: 'signed', projectName: 'PG-2026-014' },
    { id: 4, date: '31/03/2026', topic: 'Diseño de la arquitectura del sistema', description: 'Se definió la arquitectura en capas y las tecnologías a utilizar.', duration: '1h 30m', signatureStatus: 'pending', projectName: 'PG-2026-014' },
    { id: 5, date: '07/04/2026', topic: 'Implementación del módulo de autenticación', description: 'Se comenzó con la implementación del módulo de autenticación con Laravel Sanctum.', duration: '2h', signatureStatus: 'pending', projectName: 'PG-2026-014' },
    { id: 6, date: '14/04/2026', topic: 'Pruebas del módulo de usuarios', description: 'Se realizaron pruebas unitarias del CRUD de usuarios y roles.', duration: '1h', signatureStatus: 'pending', projectName: 'PG-2026-014' },
    { id: 7, date: '21/04/2026', topic: 'Integración con base de datos', description: 'Se configuró la conexión a PostgreSQL y se crearon las migraciones iniciales.', duration: '1h 30m', signatureStatus: 'unsigned', projectName: 'PG-2026-014' },
    { id: 8, date: '28/04/2026', topic: 'Despliegue del entorno de desarrollo', description: 'Se configuró Docker Compose para el entorno de desarrollo local.', duration: '1h', signatureStatus: 'unsigned', projectName: 'PG-2026-014' },
];

/* ── Signature status helpers ── */

const signatureConfig: Record<string, { label: string; variant: 'success' | 'warning' | 'inactivo' }> = {
    signed: { label: 'Firmada', variant: 'success' },
    pending: { label: 'Pendiente', variant: 'warning' },
    unsigned: { label: 'No firmado', variant: 'inactivo' },
};

/* ── Columns ── */

const columns: Column<Binnacle>[] = [
    { key: 'date', label: 'Fecha', className: 'whitespace-nowrap' },
    { key: 'topic', label: 'Tema' },
    {
        key: 'description',
        label: 'Descripción',
        className: 'max-w-xs truncate',
        render: (row) => (
            <span className="block truncate text-[#57534e]" title={row.description}>
                {row.description}
            </span>
        ),
    },
    { key: 'duration', label: 'Duración', className: 'whitespace-nowrap' },
    {
        key: 'signatureStatus',
        label: 'Estado firma',
        render: (row) => {
            const config = signatureConfig[row.signatureStatus];
            return <StatusBadge variant={config.variant}>{config.label}</StatusBadge>;
        },
    },
    {
        key: 'actions',
        label: 'Acciones',
        className: 'text-right',
        render: (row) => {
            const isSigned = row.signatureStatus === 'signed';
            return (
                <div className="inline-flex gap-0.5">
                    <button
                        className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                        aria-label={`Ver bitácora ${row.topic}`}
                        title="Ver"
                    >
                        <Eye className="h-4 w-4" />
                    </button>
                    {!isSigned && (
                        <button
                            className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[#57534e] transition-colors hover:bg-[#f5f5f4] hover:text-[#c2410c] active:scale-[0.98]"
                            aria-label={`Editar bitácora ${row.topic}`}
                            title="Editar"
                        >
                            <Pencil className="h-4 w-4" />
                        </button>
                    )}
                </div>
            );
        },
    },
];

/* ── States ── */

type PageState = 'loading' | 'empty' | 'data';

/* ── Main component ── */

export default function BitacorasEstudiante() {
    const [pageState] = useState<PageState>('data');

    function renderContent() {
        switch (pageState) {
            case 'loading':
                return (
                    <div className="flex items-center justify-center py-16">
                        <Loader2 className="h-6 w-6 animate-spin text-[#c2410c]" />
                    </div>
                );
            case 'empty':
                return (
                    <EmptyState
                        icon={FileText}
                        title="No has registrado bitácoras"
                        description="Crea una nueva bitácora para comenzar a registrar tus avances."
                        action={{ label: 'Nueva Bitácora', onClick: () => {} }}
                    />
                );
            case 'data':
                return (
                    <DataTable
                        columns={columns}
                        data={MOCK_BINNACLES}
                        getRowKey={(row) => row.id}
                        emptyMessage="No has registrado bitácoras."
                    />
                );
        }
    }

    return (
        <div className="flex flex-col gap-6">
            <PageHeader
                eyebrow="Bitácora"
                title="Bitácoras"
                subtitle="Registro de sesiones y avances de tu proyecto de grado"
                actions={
                    <button
                        className="inline-flex min-h-[40px] items-center gap-2 rounded-lg bg-[#c2410c] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#9a330a] active:scale-[0.98]"
                        aria-label="Crear nueva bitácora"
                    >
                        <Plus className="h-4 w-4" />
                        Nueva Bitácora
                    </button>
                }
            />

            {renderContent()}
        </div>
    );
}
