import { useState, useEffect } from 'react';
import { apiFetch } from '@/lib/utils';
import { Loader2, ChevronDown } from 'lucide-react';

interface Semestre {
    id: number;
    name: string;
    is_active: boolean;
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
                // Solo mostrar semestres activos, ordenados por id descendente
                const active = [...data]
                    .filter((s) => s.is_active)
                    .sort((a, b) => b.id - a.id);
                setSemestres(active);
            })
            .catch((err) => {
                if (cancelled) return;
                setError(err instanceof Error ? err.message : 'Error desconocido');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => { cancelled = true; };
    }, []);

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
        <div className="relative max-w-md">
            <select
                value={value ?? ''}
                onChange={(e) => onChange(Number(e.target.value))}
                className="w-full appearance-none rounded-lg border border-[#e5e5e5] bg-white px-4 py-2.5 pr-10 text-sm font-semibold text-[#1c1917] outline-none transition-colors hover:border-[#c2410c] focus:border-[#c2410c] focus:shadow-[0_0_0_3px_#fed7aa]"
            >
                <option value="" disabled>
                    Seleccionar semestre
                </option>
                {semestres.map((sem) => (
                    <option key={sem.id} value={sem.id}>
                        {sem.name}
                    </option>
                ))}
            </select>
            <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[#78716c]" />
        </div>
    );
}
