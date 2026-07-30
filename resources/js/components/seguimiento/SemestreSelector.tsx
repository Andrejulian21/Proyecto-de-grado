import { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/utils';
import { Loader2, ChevronDown } from 'lucide-react';

interface Semestre {
    id: number;
    nombre: string;
    activo: boolean;
}

interface SemestreSelectorProps {
    value: number | null;
    onChange: (id: number) => void;
}

export function SemestreSelector({ value, onChange }: SemestreSelectorProps) {
    const [semestres, setSemestres] = useState<Semestre[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let cancelled = false;

        apiFetch('/api/admin/semestres')
            .then(async (res) => {
                if (!res.ok) {
                    const text = await res.text().catch(() => '');
                    throw new Error(text || `Error ${res.status}: al cargar semestres`);
                }
                return res.json();
            })
            .then((json) => {
                if (cancelled) return;
                const data: Semestre[] = json.data ?? json ?? [];
                // Activos primero, luego inactivos; dentro de cada grupo, más reciente primero
                const sorted = [...data].sort((a, b) => {
                    if (a.activo !== b.activo) return a.activo ? -1 : 1;
                    return b.id - a.id;
                });
                setSemestres(sorted);
                // Auto-select if only one semester or if there's already a matching one
                if (sorted.length === 1 && value === null) {
                    onChange(sorted[0].id);
                }
            })
            .catch((err) => {
                if (cancelled) return;
                setError(err instanceof Error ? err.message : 'Error desconocido');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    if (loading) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-[#e5e5e5] bg-white px-4 py-2.5 text-sm text-[#57534e]">
                <Loader2 className="h-4 w-4 animate-spin" />
                Cargando semestres...
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center gap-2 rounded-lg border border-[#fecaca] bg-[#fee2e2] px-4 py-2.5 text-sm text-[#dc2626]">
                {error}
            </div>
        );
    }

    return (
        <div className="relative">
            <select
                value={value ?? ''}
                onChange={(e) => onChange(Number(e.target.value))}
                className="w-full appearance-none rounded-lg border border-[#e5e5e5] bg-white px-4 py-2.5 pr-10 text-sm font-medium text-[#1c1917] transition-colors hover:border-[#c2410c] focus:border-[#c2410c] focus:outline-none focus:ring-1 focus:ring-[#c2410c]"
            >
                <option value="" disabled>
                    Seleccionar semestre
                </option>
                {semestres.map((sem) => (
                    <option key={sem.id} value={sem.id}>
                        {sem.nombre} {sem.activo ? '(Activo)' : '(Inactivo)'}
                    </option>
                ))}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
        </div>
    );
}
