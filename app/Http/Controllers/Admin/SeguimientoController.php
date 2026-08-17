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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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

    /**
     * Ancho fijo (en caracteres) de la columna de observaciones; se usa
     * con wrapText en vez de autoSize porque las observaciones largas con
     * saltos de línea no se dimensionan bien con autoSize.
     */
    private const ANCHO_OBSERVACIONES = 50;

    /** Altura por línea de texto (puntos) al estimar la fila. */
    private const ALTURA_LINEA = 15;

    /**
     * Etiquetas legibles de fase para las observaciones del export.
     */
    private const ETIQUETAS_FASE = [
        'anteproyecto' => 'Anteproyecto',
        'presentacion_anteproyecto' => 'Presentación Anteproyecto',
        'desarrollo' => 'Desarrollo',
        'presentacion_final' => 'Presentación Final',
    ];

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

        $clavesEntregas = array_keys($columnasEntregas);

        $headers = array_merge(
            self::COLUMNAS_FIJAS,
            $clavesEntregas,
            ['Bitácoras PG1', 'Bitácoras PG2', 'Observaciones'],
        );

        $numCols = count($headers);
        $ultimaCol = Coordinate::stringFromColumnIndex($numCols);

        // Construimos las filas de datos primero para poder calcular totales.
        $filasDatos = [];

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

            foreach ($clavesEntregas as $clave) {
                $filaDatos[] = $estadosPorEntrega[$clave] ?? '';
            }

            $filaDatos[] = $proyecto['bitacoras_grupo_a'];
            $filaDatos[] = $proyecto['bitacoras_grupo_b'];
            $filaDatos[] = $this->observacionesTexto($proyecto['observaciones']);

            $filasDatos[] = $filaDatos;
        }

        // -- Fila de título (fila 1) -------------------------------------
        $titulo = sprintf('Seguimiento del Semestre %s', (string) ($data['semestre']['nombre'] ?? ''));
        $sheet->mergeCells('A1:'.$ultimaCol.'1');
        $sheet->setCellValue('A1', $titulo);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C2410C']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // -- Fila de encabezados de columnas (fila 2) ---------------------
        $sheet->fromArray([$headers], null, 'A2');
        $sheet->getStyle('A2:'.$ultimaCol.'2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(24);

        // -- Filas de datos (fila 3+) --------------------------------------
        $fila = 3;

        foreach ($filasDatos as $filaDatos) {
            $sheet->fromArray([$filaDatos], null, 'A'.$fila);
            $fila++;
        }

        // -- Fila de totales ------------------------------------------------
        $totales = $this->filaTotales($filasDatos, $clavesEntregas);
        $filaTotales = $fila;
        $sheet->fromArray([$totales], null, 'A'.$filaTotales);
        $sheet->getStyle('A'.$filaTotales.':'.$ultimaCol.$filaTotales)->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
            'borders' => [
                'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '4F46E5']],
            ],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // -- Bordes finos sobre encabezados + datos + totales --------------
        $sheet->getStyle('A2:'.$ultimaCol.$filaTotales)->getBorders()->applyFromArray([
            'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']],
        ]);

        // -- Color alternado de filas (zebra) sobre los datos --------------
        $zebraEnd = $filaTotales - 1;

        for ($r = 3; $r <= $zebraEnd; $r++) {
            if (($r - 3) % 2 === 1) {
                $sheet->getStyle('A'.$r.':'.$ultimaCol.$r)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F9FAFB');
            }
        }

        // -- Relleno de color por estado en las columnas de entrega --------
        $this->aplicarEstadosFondo($sheet, $filasDatos, $clavesEntregas);

        // -- Ajuste de ancho de columnas ----------------------------------
        // Observaciones usa ancho fijo + wrapText; el resto autoSize.
        $obsCol = Coordinate::stringFromColumnIndex($numCols);

        foreach (range(1, $numCols) as $i) {
            $col = Coordinate::stringFromColumnIndex($i);

            if ($col === $obsCol) {
                $sheet->getColumnDimension($col)->setWidth(self::ANCHO_OBSERVACIONES);
            } else {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        // -- Formato de la columna Observaciones ---------------------------
        $this->aplicarFormatoObservaciones($sheet, $filasDatos, $numCols);

        // Congela título + encabezado al desplazarse.
        $sheet->freezePane('A3');

        return $spreadsheet;
    }

    /**
     * Fila de totales: % de entregas "Entregado" por columna de entrega y
     * suma de bitácoras. Las columnas fijas quedan con "Totales" en la
     * primera celda; Observaciones en blanco.
     *
     * @param  array<int, array<int, mixed>>  $filasDatos
     * @param  list<string>  $clavesEntregas
     * @return array<int, mixed>
     */
    private function filaTotales(array $filasDatos, array $clavesEntregas): array
    {
        $totales = ['Totales', '', '', ''];

        foreach ($clavesEntregas as $i => $clave) {
            $idx = 4 + $i;
            $entregados = 0;
            $conDato = 0;

            foreach ($filasDatos as $filaDatos) {
                if (($filaDatos[$idx] ?? '') !== '') {
                    $conDato++;

                    if ($filaDatos[$idx] === 'Entregado') {
                        $entregados++;
                    }
                }
            }

            $totales[] = $conDato > 0 ? round(($entregados / $conDato) * 100).'%' : '';
        }

        $bitA = 4 + count($clavesEntregas);
        $bitB = $bitA + 1;
        $totales[] = array_sum(array_column($filasDatos, $bitA));
        $totales[] = array_sum(array_column($filasDatos, $bitB));
        $totales[] = '';

        return $totales;
    }

    /**
     * Aplica un relleno de color por estado en las celdas de entrega:
     * Entregado (verde), Pendiente (ámbar), No entregó (rojo claro).
     *
     * @param  array<int, array<int, mixed>>  $filasDatos
     * @param  list<string>  $clavesEntregas
     */
    private function aplicarEstadosFondo(Worksheet $sheet, array $filasDatos, array $clavesEntregas): void
    {
        $estadoFondos = [
            'Entregado' => 'DCFCE7',
            'Pendiente' => 'FEF3C7',
            'No entregó' => 'FEE2E2',
        ];

        foreach ($filasDatos as $r => $filaDatos) {
            $filaAbs = 3 + $r;

            foreach ($clavesEntregas as $i => $clave) {
                $idx = 4 + $i;
                $valor = $filaDatos[$idx] ?? '';

                if ($valor === '' || ! isset($estadoFondos[$valor])) {
                    continue;
                }

                $celda = Coordinate::stringFromColumnIndex($idx + 1).$filaAbs;
                $sheet->getStyle($celda)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB($estadoFondos[$valor]);
            }
        }
    }

    /**
     * Concatena las observaciones por fase en una sola celda, con la
     * etiqueta legible de la fase y salto de línea entre fases.
     *
     * @param  array<int, array{fase: string, contenido: string}>  $observaciones
     */
    private function observacionesTexto(array $observaciones): string
    {
        return implode("\n", array_map(
            fn (array $obs): string => trim(
                (self::ETIQUETAS_FASE[$obs['fase']] ?? $obs['fase']).': '.$obs['contenido']
            ),
            $observaciones,
        ));
    }

    /**
     * Aplica wrapText + alineación arriba-izquierda a la columna de
     * observaciones y ajusta la altura de cada fila de datos según el
     * contenido visible, para que el texto largo se lea sin cortarse.
     * La zebra y los bordes ya fueron aplicados sobre toda la tabla.
     *
     * @param  array<int, array<int, mixed>>  $filasDatos
     */
    private function aplicarFormatoObservaciones(Worksheet $sheet, array $filasDatos, int $numCols): void
    {
        $obsCol = Coordinate::stringFromColumnIndex($numCols);
        $obsIdx = $numCols - 1;

        foreach ($filasDatos as $r => $filaDatos) {
            $filaAbs = 3 + $r;
            $texto = (string) ($filaDatos[$obsIdx] ?? '');

            $sheet->getStyle($obsCol.$filaAbs)->getAlignment()->applyFromArray([
                'wrapText' => true,
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_TOP,
            ]);

            if ($texto !== '') {
                $sheet->getRowDimension($filaAbs)->setRowHeight(
                    $this->alturaParaTexto($texto, self::ANCHO_OBSERVACIONES)
                );
            }
        }
    }

    /**
     * Estima la altura (en puntos) que necesita una celda con wrapText
     * para mostrar `$texto` en una columna de `$anchoCol` caracteres.
     */
    private function alturaParaTexto(string $texto, int $anchoCol): int
    {
        $ancho = max($anchoCol - 2, 5);
        $lineas = 0;

        foreach (explode("\n", $texto) as $linea) {
            $lineas += max(1, (int) ceil(mb_strlen($linea) / $ancho));
        }

        return max($lineas * self::ALTURA_LINEA, self::ALTURA_LINEA);
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
