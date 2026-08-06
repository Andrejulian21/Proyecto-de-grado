<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeguimientoObservacion;
use App\Models\Semestre;
use App\Services\SeguimientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SeguimientoController extends Controller
{
    public const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public const MENSAJE_SIN_LIBRERIA = 'Error al generar el archivo Excel. Verifique que la librería esté instalada.';

    /**
     * Etiquetas de estado por entrega (RF-EX-01): el service devuelve
     * claves internas y la exportación muestra los labels de la UI.
     */
    private const ESTADOS_LABEL = [
        'entregada' => 'Entregado',
        'pendiente' => 'Pendiente',
        'no_entrego' => 'No entregó',
    ];

    private const COLUMNAS_FIJAS = ['Estudiantes', 'Proyecto', 'Código', 'Director'];

    public function __construct(
        private readonly SeguimientoService $seguimientoService,
    ) {}

    /**
     * Get full seguimiento data for all projects in a semester.
     */
    public function porSemestre(Semestre $semestre): JsonResponse
    {
        $data = $this->seguimientoService->obtenerSeguimiento($semestre->id);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/admin/seguimiento/semestre/{semestre}/export (RF-EX-01, D5).
     *
     * Genera un .xlsx con una fila por proyecto y columnas: estudiante,
     * proyecto, director, estado por fase/entrega, bitácoras y
     * observaciones. Nombre: `Seguimiento del [Grupo] [YYYY-MM-DD_HH-mm].xlsx`.
     */
    public function exportar(Semestre $semestre): StreamedResponse|JsonResponse
    {
        try {
            $data = $this->seguimientoService->obtenerSeguimiento($semestre->id);
            $spreadsheet = $this->construirSpreadsheet($data);
        } catch (Throwable) {
            return response()->json(['error' => self::MENSAJE_SIN_LIBRERIA], 500);
        }

        $filename = $this->nombreArchivoExport($data['semestre']);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                $writer = new XlsxWriter($spreadsheet);
                $writer->save('php://output');
            },
            $filename,
            ['Content-Type' => self::MIME_XLSX],
        );
    }

    /**
     * Construye el libro con la estructura del tab Seguimiento:
     * columnas fijas + una columna por entrega (fase · título) con su
     * estado + bitácoras PG1/PG2 + observaciones.
     *
     * @param  array{semestre: array, proyectos: array}  $data
     */
    private function construirSpreadsheet(array $data): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Seguimiento');

        // Unión de columnas de entregas a través de todos los proyectos.
        $columnasEntregas = [];

        foreach ($data['proyectos'] as $proyecto) {
            foreach ($proyecto['fases'] as $fase) {
                foreach ($fase['entregas'] as $entrega) {
                    $columnasEntregas[$fase['fase'].' - '.$entrega['title']] = true;
                }
            }
        }

        $headers = array_merge(
            self::COLUMNAS_FIJAS,
            array_keys($columnasEntregas),
            ['Bitácoras PG1', 'Bitácoras PG2', 'Observaciones'],
        );
        $sheet->fromArray([$headers], null, 'A1');

        $fila = 2;

        foreach ($data['proyectos'] as $proyecto) {
            $estadosPorEntrega = [];

            foreach ($proyecto['fases'] as $fase) {
                foreach ($fase['entregas'] as $entrega) {
                    $clave = $fase['fase'].' - '.$entrega['title'];
                    $estadosPorEntrega[$clave] = self::ESTADOS_LABEL[$entrega['estado']] ?? $entrega['estado'];
                }
            }

            $filaDatos = [
                $proyecto['estudiantes'],
                $proyecto['proyecto_nombre'],
                $proyecto['proyecto_codigo'],
                $proyecto['director'],
            ];

            foreach (array_keys($columnasEntregas) as $clave) {
                $filaDatos[] = $estadosPorEntrega[$clave] ?? '';
            }

            $filaDatos[] = $proyecto['bitacoras_grupo_a'];
            $filaDatos[] = $proyecto['bitacoras_grupo_b'];
            $filaDatos[] = $this->observacionesTexto($proyecto['observaciones']);

            $sheet->fromArray([$filaDatos], null, 'A'.$fila);
            $fila++;
        }

        return $spreadsheet;
    }

    /**
     * Concatena las observaciones por fase en una sola celda.
     *
     * @param  array<int, array{fase: string, contenido: string}>  $observaciones
     */
    private function observacionesTexto(array $observaciones): string
    {
        return implode("\n", array_map(
            fn (array $obs): string => trim($obs['fase'].': '.$obs['contenido']),
            $observaciones,
        ));
    }

    /**
     * D5: `Seguimiento del [Grupo] [YYYY-MM-DD HHmm].xlsx`. Reemplaza
     * caracteres inválidos de nombre de archivo (`/ \ : * ? " < > |` y
     * control chars) por espacio; el semestre sin nombre cae a 'Semestre'.
     *
     * @param  array{nombre: string}  $semestre
     */
    private function nombreArchivoExport(array $semestre): string
    {
        $grupo = (string) ($semestre['nombre'] ?? '');
        $grupo = preg_replace('/[\\\\\/:*?"<>|\x00-\x1F]+/', ' ', $grupo);
        $grupo = trim((string) $grupo) ?: 'Semestre';

        return sprintf('Seguimiento del %s %s.xlsx', $grupo, now()->format('Y-m-d_H-i'));
    }

    /**
     * Upsert a seguimiento observation for a (project, semester, phase) tuple.
     *
     * Expects JSON body:
     *   { proyecto_id: int, semestre_id: int, fase: string, observacion: string|null }
     */
    public function guardarObservacion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'proyecto_id' => 'required|integer|exists:proyectos,id',
            'semestre_id' => 'required|integer|exists:semestres,id',
            'fase' => 'required|string|max:50',
            'observacion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $observacion = SeguimientoObservacion::updateOrCreate(
            [
                'proyecto_id' => $request->integer('proyecto_id'),
                'semestre_id' => $request->integer('semestre_id'),
                'fase' => $request->input('fase'),
            ],
            [
                'observacion' => $request->input('observacion'),
            ]
        );

        return response()->json(['data' => $observacion], 200);
    }
}
