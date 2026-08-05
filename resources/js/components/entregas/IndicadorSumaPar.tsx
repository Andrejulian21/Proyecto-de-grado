import { AlertTriangle } from 'lucide-react';
import type { Entrega, Fase } from '@/hooks/useEntregas';

/**
 * Visual indicator of the accumulated `grade_percentage` for the phase pair
 * of the semester (RF-ENT-05): green when ≤ 100, red when > 100, plus a
 * warning listing the pair phases that still lack a percentage.
 *
 * The value shown follows the backend semantics of `EntregaPesoService`:
 * for an edit the entrega's own current value is excluded ($excluirId) so
 * the proposed value is not double-counted.
 */

export interface ResultadoIndicadorPar {
    suma: number;
    faltantes: Fase[];
    excede: boolean;
}

/** Phase pairs at semester level (RF-ENT-04) — mirrors EntregaPesoService. */
export const PARES_FASES: Record<Fase, [Fase, Fase]> = {
    anteproyecto: ['anteproyecto', 'presentacion_anteproyecto'],
    presentacion_anteproyecto: ['anteproyecto', 'presentacion_anteproyecto'],
    desarrollo: ['desarrollo', 'presentacion_final'],
    presentacion_final: ['desarrollo', 'presentacion_final'],
};

const FASE_LABELS: Record<Fase, string> = {
    anteproyecto: 'Anteproyecto',
    presentacion_anteproyecto: 'Presentación Anteproyecto',
    desarrollo: 'Desarrollo del proyecto',
    presentacion_final: 'Presentación Final',
};

export function calcularIndicadorPar(
    entregas: Entrega[],
    fase: Fase,
    valorForm: string,
    excluirId?: number,
): ResultadoIndicadorPar {
    const par = PARES_FASES[fase] ?? [fase];
    const propuesto = valorForm === '' ? 0 : Number(valorForm);
    const valor = Number.isFinite(propuesto) ? propuesto : 0;

    const delPar = entregas.filter(
        (e) => (par as Fase[]).includes(e.phase as Fase) && e.id !== excluirId,
    );

    const suma =
        delPar.reduce(
            (acc, e) => acc + (e.grade_percentage != null ? Number(e.grade_percentage) : 0),
            0,
        ) + valor;

    const faltantes = (par as Fase[]).filter((f) => {
        if (f === fase && valorForm !== '') return false;
        return !delPar.some((e) => e.phase === f && e.grade_percentage != null);
    });

    return { suma, faltantes, excede: suma > 100 };
}

interface Props {
    entregas: Entrega[];
    fase: Fase;
    valorForm: string;
    excluirId?: number;
}

export default function IndicadorSumaPar({ entregas, fase, valorForm, excluirId }: Props) {
    const { suma, faltantes, excede } = calcularIndicadorPar(entregas, fase, valorForm, excluirId);

    return (
        <div className="flex flex-col gap-1">
            <p
                className={`text-sm font-semibold tabular-nums ${
                    excede ? 'text-[#dc2626]' : 'text-[#16a34a]'
                }`}
            >
                {excede
                    ? `Suma del par: ${suma}% (excede 100%)`
                    : `Suma del par: ${suma}% / 100%`}
            </p>

            {faltantes.length > 0 && (
                <p
                    className="flex items-start gap-1.5 text-xs font-medium text-[#d97706]"
                    role="status"
                >
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                    <span>
                        Par incompleto: falta asignar % en la(s) fase(s){' '}
                        {faltantes.map((f) => `'${FASE_LABELS[f] ?? f}'`).join(', ')}
                    </span>
                </p>
            )}
        </div>
    );
}
