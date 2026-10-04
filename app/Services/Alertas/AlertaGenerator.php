<?php

declare(strict_types=1);

namespace App\Services\Alertas;

use App\Enums\EstadoEntrega;
use App\Enums\EstadoFirma;
use App\Enums\SeveridadAlerta;
use App\Enums\TipoAlerta;
use App\Models\Alerta;
use App\Models\Bitacora;
use App\Models\Entrega;
use App\Models\EntregaProyecto;
use App\Models\User;
use App\Models\VersionDocumento;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Derives the coordinator's alerts from current state and persists them.
 *
 * The generator is a reconciliation, not an append-only log: it upserts every
 * alert that currently applies and deletes the ones that stopped applying.
 * Idempotency comes from `clave` — a stable, unique identity per alert — so
 * running this twice changes nothing and running it after a partial change
 * converges instead of duplicating.
 *
 * Two invariants are load-bearing:
 *
 *  1. `reviewed_at` / `reviewed_by` are never touched by regeneration. A review
 *     is human work; regenerating must not erase it.
 *  2. R2 is scoped through the `entrega_proyecto` pivot. An `entrega` is a
 *     semester-wide template shared by every linked project, so the unscoped
 *     `Entrega::versiones()` relation leaks one project's uploads to all of
 *     them (documented in `App\Models\Entrega::versiones()`). Only the pivot
 *     answers "did THIS project submit THIS delivery".
 */
class AlertaGenerator
{
    /** R1 — a bitácora is expected to be signed within one hour. */
    public const HORAS_BITACORA_SIN_FIRMAR = 1;

    /** R1 — past this age an unsigned bitácora becomes critical. */
    public const HORAS_BITACORA_CRITICA = 24;

    /**
     * R3 — a bitácora is signed after a one-hour session with the students, so
     * signatures closer than this are the anomaly, not the norm.
     */
    public const VENTANA_FIRMAS_MINUTOS = 60;

    /** R3 — two signatures inside the window is already suspicious. */
    public const UMBRAL_FIRMAS = 2;

    /**
     * Reconcile `alertas` with the current state.
     */
    public function generar(): void
    {
        $vigentes = array_merge(
            $this->alertasBitacoraSinFirmar(),
            $this->alertasEntregaVencida(),
            $this->alertasFirmasSospechosas(),
        );

        DB::transaction(function () use ($vigentes): void {
            $this->upsert($vigentes);
            $this->eliminarObsoletas($vigentes);
        });
    }

    /**
     * Insert alerts that are new and refresh the ones whose facts changed.
     *
     * @param  list<array{clave: string, tipo: TipoAlerta, mensaje: string, proyecto_id: int|null, severidad: SeveridadAlerta, datos: array<string, mixed>}>  $vigentes
     */
    private function upsert(array $vigentes): void
    {
        if ($vigentes === []) {
            return;
        }

        $existentes = Alerta::query()
            ->whereIn('clave', array_column($vigentes, 'clave'))
            ->get()
            ->keyBy('clave');

        foreach ($vigentes as $alerta) {
            $actual = $existentes->get($alerta['clave']);

            if (! $actual instanceof Alerta) {
                Alerta::create($alerta);

                continue;
            }

            if (! $this->cambia($actual, $alerta)) {
                continue;
            }

            // `reviewed_at` and `reviewed_by` are deliberately absent: they are
            // the only columns a human owns.
            $actual->update([
                'mensaje' => $alerta['mensaje'],
                'severidad' => $alerta['severidad'],
                'proyecto_id' => $alerta['proyecto_id'],
                'datos' => $alerta['datos'],
            ]);
        }
    }

    /**
     * Did any generator-owned field actually move?
     *
     * Skipping no-op writes keeps `updated_at` meaningful and avoids touching
     * rows that are already correct.
     *
     * @param  array{clave: string, tipo: TipoAlerta, mensaje: string, proyecto_id: int|null, severidad: SeveridadAlerta, datos: array<string, mixed>}  $nueva
     */
    private function cambia(Alerta $actual, array $nueva): bool
    {
        return $actual->mensaje !== $nueva['mensaje']
            || $actual->severidad !== $nueva['severidad']
            || $actual->proyecto_id !== $nueva['proyecto_id']
            || ($actual->datos ?? []) != $nueva['datos'];
    }

    /**
     * Drop alerts whose underlying condition no longer holds.
     *
     * Reviewed alerts are deleted too: once the condition is gone, keeping a
     * dismissed row around only invites the coordinator to review a ghost.
     *
     * @param  list<array<string, mixed>>  $vigentes
     */
    private function eliminarObsoletas(array $vigentes): void
    {
        Alerta::query()
            ->when(
                $vigentes !== [],
                fn ($query) => $query->whereNotIn('clave', array_column($vigentes, 'clave')),
            )
            ->delete();
    }

    /**
     * R1 — bitácoras still unsigned after the expected signing window.
     *
     * `bitacoras` has no author column, only `proyecto_id`, and the signature
     * state lives in `signature_status` / `director_signed_at` /
     * `student_signed_at`. A bitácora counts as signed only once the director
     * signed it (`FirmadaDirector`, the same condition as
     * `Bitacora::hasValidSignature()`), so `Pendiente`, `NoFirmada` and
     * `FirmadaEstudiante` are all still open.
     *
     * @return list<array{clave: string, tipo: TipoAlerta, mensaje: string, proyecto_id: int|null, severidad: SeveridadAlerta, datos: array<string, mixed>}>
     */
    private function alertasBitacoraSinFirmar(): array
    {
        $firmadas = [
            EstadoFirma::FirmadaDirector->value,
            EstadoFirma::Completada->value,
        ];

        return Bitacora::query()
            ->whereNotIn('signature_status', $firmadas)
            ->where('created_at', '<=', now()->subHours(self::HORAS_BITACORA_SIN_FIRMAR))
            ->get(['id', 'proyecto_id', 'created_at'])
            ->map(function (Bitacora $bitacora): array {
                $minutos = (int) $bitacora->created_at->diffInMinutes(now());
                $horas = intdiv($minutos, 60);

                return [
                    'clave' => "bitacora_sin_firmar:{$bitacora->id}",
                    'tipo' => TipoAlerta::BitacoraSinFirmar,
                    'mensaje' => "Bitácora #{$bitacora->id} del proyecto #{$bitacora->proyecto_id} lleva {$horas} h sin firma del director.",
                    'proyecto_id' => $bitacora->proyecto_id,
                    'severidad' => $minutos > self::HORAS_BITACORA_CRITICA * 60
                        ? SeveridadAlerta::Alta
                        : SeveridadAlerta::Media,
                    'datos' => [
                        'bitacora_id' => $bitacora->id,
                        'proyecto_id' => $bitacora->proyecto_id,
                        'creada_at' => $bitacora->created_at->toIso8601String(),
                        'minutos_sin_firmar' => $minutos,
                    ],
                ];
            })
            ->all();
    }

    /**
     * R2 — deliveries whose window closed with nothing uploaded.
     *
     * Aligned with `EstadoEntregaNota::NoEntregada`: deadline elapsed AND no
     * version on this project's pivot. A still-`solicitada` entrega is
     * `NoIniciada`, not `NoEntregada` — it never reached a student, so flagging
     * it would punish a deadline that was never opened.
     *
     * @return list<array{clave: string, tipo: TipoAlerta, mensaje: string, proyecto_id: int|null, severidad: SeveridadAlerta, datos: array<string, mixed>}>
     */
    private function alertasEntregaVencida(): array
    {
        $alertas = [];

        $pivotes = EntregaProyecto::query()->with('entrega')->get();

        foreach ($pivotes as $pivot) {
            $entrega = $pivot->entrega;

            if (! $entrega instanceof Entrega) {
                continue;
            }

            if (! $this->plazoVencido($entrega) || ! $this->habilitada($entrega)) {
                continue;
            }

            if ($this->tieneVersiones($pivot)) {
                continue;
            }

            $alertas[] = [
                'clave' => "entrega_vencida:{$pivot->id}",
                'tipo' => TipoAlerta::EntregaVencida,
                'mensaje' => "Entrega \"{$entrega->title}\" venció el {$this->fechaLegible($entrega)} y el proyecto #{$pivot->proyecto_id} no subió ninguna versión.",
                'proyecto_id' => $pivot->proyecto_id,
                'severidad' => SeveridadAlerta::Alta,
                'datos' => [
                    'entrega_id' => $entrega->id,
                    'entrega_proyecto_id' => $pivot->id,
                    'proyecto_id' => $pivot->proyecto_id,
                    'titulo_entrega' => $entrega->title,
                    'due_date' => $entrega->due_date?->toDateString(),
                    'hora_maxima' => $entrega->hora_maxima,
                ],
            ];
        }

        return $alertas;
    }

    /**
     * R3 — directors signing several bitácoras inside a short window.
     *
     * `bitacoras` stores one `director_signed_at` per row and there is no
     * signature-history table, so each signed bitácora is treated as exactly
     * one signature event and the director is resolved through
     * `proyectos.director_id`. Detection uses a true moving window; only the
     * `clave` is bucketed by hour, which is what keeps the identity stable
     * across runs.
     *
     * @return list<array{clave: string, tipo: TipoAlerta, mensaje: string, proyecto_id: int|null, severidad: SeveridadAlerta, datos: array<string, mixed>}>
     */
    private function alertasFirmasSospechosas(): array
    {
        $firmas = DB::table('bitacoras')
            ->join('proyectos', 'proyectos.id', '=', 'bitacoras.proyecto_id')
            ->whereNotNull('bitacoras.director_signed_at')
            ->whereNotNull('proyectos.director_id')
            ->orderBy('proyectos.director_id')
            ->orderBy('bitacoras.director_signed_at')
            ->get([
                'proyectos.director_id',
                'bitacoras.id as bitacora_id',
                'bitacoras.director_signed_at',
            ]);

        if ($firmas->isEmpty()) {
            return [];
        }

        $nombres = User::query()
            ->whereIn('id', $firmas->pluck('director_id')->unique())
            ->pluck('name', 'id');

        $alertas = [];

        foreach ($firmas->groupBy('director_id') as $directorId => $registros) {
            $directorId = (int) $directorId;
            $nombre = (string) ($nombres->get($directorId) ?? "usuario #{$directorId}");

            $firmasOrdenadas = $registros
                ->map(fn ($registro) => [
                    'bitacora_id' => (int) $registro->bitacora_id,
                    'momento' => Carbon::parse($registro->director_signed_at),
                ])
                ->sortBy('momento')
                ->values();

            $buckets = [];

            foreach ($firmasOrdenadas as $indice => $firma) {
                $limite = $firma['momento']->copy()->subMinutes(self::VENTANA_FIRMAS_MINUTOS);

                $enVentana = $firmasOrdenadas->filter(
                    fn (array $otra): bool => $otra['momento']->greaterThanOrEqualTo($limite)
                        && $otra['momento']->lessThanOrEqualTo($firma['momento']),
                );

                if ($enVentana->count() < self::UMBRAL_FIRMAS) {
                    continue;
                }

                $bucket = $firma['momento']->format('Y-m-d-H');

                // One alert per director per hour bucket, however many windows
                // inside that hour crossed the threshold.
                if (isset($buckets[$bucket])) {
                    continue;
                }

                $buckets[$bucket] = true;

                $alertas[] = [
                    'clave' => "firmas_sospechosas:{$directorId}:{$bucket}",
                    'tipo' => TipoAlerta::FirmasSospechosas,
                    'mensaje' => "El director {$nombre} registró {$enVentana->count()} firmas de bitácora en menos de ".self::VENTANA_FIRMAS_MINUTOS.' minutos.',
                    'proyecto_id' => null,
                    'severidad' => SeveridadAlerta::Media,
                    'datos' => [
                        'director_id' => $directorId,
                        'ventana_minutos' => self::VENTANA_FIRMAS_MINUTOS,
                        'firmas' => $enVentana->count(),
                        'bitacora_ids' => $enVentana->pluck('bitacora_id')->values()->all(),
                        'ventana_inicio' => $limite->toIso8601String(),
                        'ventana_fin' => $firma['momento']->toIso8601String(),
                    ],
                ];
            }
        }

        return $alertas;
    }

    /**
     * Did the submission window close?
     *
     * Mirrors `NotaEntregaResolver::plazoVencido`, which in turn replicates
     * `ConsultaNotasService::plazoVencido`: `due_date` is cast to date, so the
     * comparison is on whole days and `isPast()` is never used — a delivery due
     * TODAY is not a miss yet. With `hora_maxima` the window ends at
     * `due_date + hora_maxima` instead, so the exact timestamp is the only
     * correct comparison. The value is a free-form string column, hence the
     * manual parse instead of a date parser that could throw mid-run.
     */
    private function plazoVencido(Entrega $entrega): bool
    {
        if ($entrega->due_date === null) {
            return false;
        }

        $horaMaxima = $entrega->hora_maxima === null ? null : trim((string) $entrega->hora_maxima);

        if ($horaMaxima === null || $horaMaxima === '') {
            return $entrega->due_date->lt(now()->startOfDay());
        }

        $partes = explode(':', $horaMaxima);

        return $entrega->due_date->copy()
            ->setTime(
                (int) ($partes[0] ?? 0),
                (int) ($partes[1] ?? 0),
                (int) ($partes[2] ?? 0),
            )
            ->isPast();
    }

    /**
     * Did the delivery ever reach a student?
     *
     * Habilitación lives on the semester-wide template (`entregas.status`),
     * not per project: `solicitada` is the pre-habilitación state, and every
     * upload path promotes it out of `solicitada` before writing a version.
     */
    private function habilitada(Entrega $entrega): bool
    {
        return $entrega->status !== EstadoEntrega::Solicitada;
    }

    /**
     * Has this project uploaded anything for this delivery?
     *
     * The boundary is the pivot. `VersionDocumento::scopeParaProyecto()` would
     * count uploads made on OTHER entregas of the same project, and
     * `Entrega::versiones()` is the semester-wide relation across every linked
     * project. Versions whose pivot was cleared belong to no project and are
     * excluded for free.
     */
    private function tieneVersiones(EntregaProyecto $pivot): bool
    {
        return VersionDocumento::query()
            ->where('entrega_proyecto_id', $pivot->id)
            ->exists();
    }

    private function fechaLegible(Entrega $entrega): string
    {
        $fecha = $entrega->due_date?->toDateString() ?? 'sin fecha';

        return $entrega->hora_maxima === null || trim((string) $entrega->hora_maxima) === ''
            ? $fecha
            : "{$fecha} {$entrega->hora_maxima}";
    }
}
