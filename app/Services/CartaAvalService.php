<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\GenerateCartAction;
use App\Models\Entrega;
use App\Models\EvaluadorProyecto;
use App\Models\Proyecto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Domain service for the director cartas de aval feature (PR 1).
 *
 * Responsibilities:
 *  - Habilitación temporal de las cartas (D3): las cartas se habilitan
 *    cuando `now() >= max(cierre_efectivo)` de las entregas en fase
 *    `desarrollo` del semestre del proyecto, donde
 *    `cierre_efectivo = due_date + (hora_maxima ?? '23:59:59')`.
 *  - Resolución de placeholders por carta (D1/D2/D4) y jurados derivados
 *    de `evaluador_proyecto.fase = 'presentacion_final'`.
 *  - Listado de proyectos del director con habilitación y warnings.
 *  - Generación de los DOCX delegando en GenerateCartAction.
 */
final class CartaAvalService
{
    public const FASE_ENTREGAS = 'desarrollo';

    public const FASE_JURADOS = 'presentacion_final';

    public const HORA_FIN_DIA = '23:59:59';

    public const WARNING_SIN_JURADOS = 'Faltan asignaciones de jurados para presentación final';

    public const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public const CARTA_AVAL = 'aval';

    public const CARTA_JURADOS = 'jurados';

    public function __construct(private readonly GenerateCartAction $generateCart) {}

    /**
     * Cierre efectivo = due_date + (hora_maxima ?? '23:59:59') de la
     * entrega `desarrollo` más tardía. Null cuando no hay entregas.
     *
     * @param  iterable<Entrega>  $entregasDesarrollo
     */
    public function cierreEfectivo(iterable $entregasDesarrollo): ?Carbon
    {
        $cierre = null;

        foreach ($entregasDesarrollo as $entrega) {
            if ($entrega->due_date === null) {
                continue;
            }

            $hora = $entrega->hora_maxima ?: self::HORA_FIN_DIA;
            $efectivo = $entrega->due_date->copy()->setTimeFromTimeString($hora);

            if ($cierre === null || $efectivo->greaterThan($cierre)) {
                $cierre = $efectivo;
            }
        }

        return $cierre;
    }

    /**
     * D3: las cartas se habilitan cuando now >= cierre efectivo de la
     * última entrega `desarrollo` del semestre.
     *
     * @param  iterable<Entrega>  $entregasDesarrollo
     */
    public function calcularHabilitacion(iterable $entregasDesarrollo, ?Carbon $now = null): bool
    {
        $cierre = $this->cierreEfectivo($entregasDesarrollo);

        if ($cierre === null) {
            return false;
        }

        return ($now ?? Carbon::now())->greaterThanOrEqualTo($cierre);
    }

    /**
     * Nombres de jurados asignados a la fase de presentación final (D2).
     *
     * @return Collection<int, string>
     */
    public function obtenerJurados(Proyecto $proyecto): Collection
    {
        // The coordinator assignment module stores fase as 'Anteproyecto'/'Final'
        // while the canonical domain values are 'presentacion_anteproyecto'/
        // 'presentacion_final'. Accept both so jurados assigned from either
        // surface resolve for the final presentation letter.
        return EvaluadorProyecto::query()
            ->where('proyecto_id', $proyecto->id)
            ->whereIn('fase', [self::FASE_JURADOS, 'Final'])
            ->with('evaluador:id,name')
            ->orderBy('id')
            ->get()
            ->pluck('evaluador.name')
            ->filter(fn (?string $name) => $name !== null && $name !== '')
            ->values();
    }

    /**
     * Resuelve los placeholders de una carta para un estudiante (D1/D2).
     *
     * Carta `aval` (Carta 1): nombre/código del estudiante, título del
     * proyecto, jurados 1..3 y director. Si faltan jurados, los
     * placeholders quedan vacíos (tabla de firmas vacía) y se agrega el
     * warning D2. La carta se genera igual.
     *
     * Carta `jurados` (Carta 2): nombre/código del estudiante, título y
     * director. La cédula NO se resuelve — el template conserva el
     * placeholder literal `[Número de documento]` (D1).
     *
     * @param  list<string>  $jurados
     * @return array{placeholders: array<string, string>, warnings: list<string>}
     */
    public function resolverPlaceholders(Proyecto $proyecto, User $estudiante, string $carta, array $jurados = []): array
    {
        $base = [
            'nombre_estudiante' => $estudiante->name ?? '',
            'codigo_estudiante' => $estudiante->codigo_estudiante ?? '',
            'titulo_proyecto' => $proyecto->title ?? '',
            'nombre_director' => $proyecto->director?->name ?? '',
            'ciudad' => 'Bucaramanga',
            'fecha' => Carbon::now()->locale('es')->isoFormat('D [de] MMMM [de] YYYY'),
        ];

        if ($carta !== self::CARTA_AVAL) {
            return ['placeholders' => $base, 'warnings' => []];
        }

        $jurado1 = $jurados[0] ?? '';
        $jurado2 = $jurados[1] ?? '';
        $jurado3 = $jurados[2] ?? '';

        $warnings = [];

        if ($jurado1 === '' || $jurado2 === '' || $jurado3 === '') {
            $warnings[] = self::WARNING_SIN_JURADOS;
        }

        return [
            'placeholders' => $base + [
                'jurado_1_nombre' => $jurado1,
                'jurado_2_nombre' => $jurado2,
                'jurado_3_nombre' => $jurado3,
            ],
            'warnings' => $warnings,
        ];
    }

    /**
     * Proyectos del director (semestres activos) con habilitación de
     * cartas, cierre efectivo, estudiantes y warnings (D1/D3/D4).
     *
     * @return list<array<string, mixed>>
     */
    public function listarProyectosConHabilitacion(User $director): array
    {
        $proyectos = Proyecto::query()
            ->where('director_id', $director->id)
            ->enSemestresActivos()
            ->with(['semestre:id,name,is_active', 'director:id,name'])
            ->with(['estudiantes' => fn ($q) => $q->orderBy('users.name')->select('users.id', 'users.name', 'users.codigo_estudiante')])
            ->orderBy('title')
            ->get();

        $semestreIds = $proyectos->pluck('semester_id')->filter()->unique()->values();

        $entregasPorSemestre = Entrega::query()
            ->whereIn('semester_id', $semestreIds)
            ->where('phase', self::FASE_ENTREGAS)
            ->get(['id', 'semester_id', 'due_date', 'hora_maxima'])
            ->groupBy('semester_id');

        return $proyectos
            ->map(function (Proyecto $proyecto) use ($entregasPorSemestre): array {
                $entregas = $entregasPorSemestre->get($proyecto->semester_id, collect());
                $cierre = $this->cierreEfectivo($entregas);
                $jurados = $this->obtenerJurados($proyecto);
                $warningJurados = $jurados->count() >= 3 ? [] : [self::WARNING_SIN_JURADOS];

                return [
                    'id' => $proyecto->id,
                    'code' => $proyecto->code,
                    'title' => $proyecto->title,
                    'cartas_habilitadas' => $this->calcularHabilitacion($entregas),
                    'cierre_efectivo' => $cierre?->toIso8601String(),
                    'estudiantes' => $proyecto->estudiantes
                        ->map(fn (User $estudiante): array => [
                            'id' => $estudiante->id,
                            'name' => $estudiante->name,
                            'codigo_estudiante' => $estudiante->codigo_estudiante,
                            'warnings' => $warningJurados,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Genera la Carta 1 (aval sustentación pública) para un estudiante.
     *
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function generarAvalSustentacion(Proyecto $proyecto, User $estudiante): array
    {
        $jurados = $this->obtenerJurados($proyecto)->values()->all();
        $resuelto = $this->resolverPlaceholders($proyecto, $estudiante, self::CARTA_AVAL, $jurados);

        $path = $this->generateCart->handle(
            $this->templatePath('aval-sustentacion.docx'),
            $resuelto['placeholders'],
        );

        return [
            'path' => $path,
            'filename' => $this->nombreArchivo('Aval Sustentacion Publica', $estudiante),
            'warnings' => $resuelto['warnings'],
        ];
    }

    /**
     * Genera la Carta 2 (aval entrega a jurados) para un estudiante.
     *
     * @return array{path: string, filename: string, warnings: list<string>}
     */
    public function generarCartaJurados(Proyecto $proyecto, User $estudiante): array
    {
        $resuelto = $this->resolverPlaceholders($proyecto, $estudiante, self::CARTA_JURADOS);

        $path = $this->generateCart->handle(
            $this->templatePath('carta-jurados.docx'),
            $resuelto['placeholders'],
        );

        return [
            'path' => $path,
            'filename' => $this->nombreArchivo('Carta de Aval Entrega a Jurados', $estudiante),
            'warnings' => $resuelto['warnings'],
        ];
    }

    /**
     * D4: `Prefijo [Nombre Apellido].docx`. Caracteres inválidos para
     * nombres de archivo de Windows (`/ \ : * ? " < > |`) se reemplazan
     * por espacios.
     */
    public function nombreArchivo(string $prefijo, User $estudiante): string
    {
        $nombre = preg_replace('/[\\\\\/:*?"<>|]+/', ' ', (string) $estudiante->name);
        $nombre = trim((string) $nombre) ?: 'Estudiante';

        return sprintf('%s [%s].docx', $prefijo, $nombre);
    }

    /**
     * Ruta absoluta de un template DOCX en storage/app/templates.
     */
    public function templatePath(string $archivo): string
    {
        return Storage::disk('local')->path('templates/'.$archivo);
    }
}
