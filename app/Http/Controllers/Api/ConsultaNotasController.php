<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CoordinadorGradeWeight;
use App\Services\ConsultaNotasService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ConsultaNotasController extends Controller
{
    private const MIME_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    private const HEADER_FILL = 'FFF7ED';
    private const HEADER_TEXT_COLOR = '57534E';
    private const FINAL_GRADE_FILL = 'FFF7ED';
    private const BORDER_COLOR = 'E5E5E5';
    private const MENSAJE_SIN_LIBRERIA = 'Error al generar el archivo Excel. Verifique que la librería esté instalada.';

    public function __construct(
        private readonly ConsultaNotasService $consultaNotas,
    ) {}

    /**
     * GET /api/notas
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semestre_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'proyecto_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'entrega_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'estado_nota' => ['sometimes', 'nullable', 'in:calificada,sin_calificar'],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tipo' => ['sometimes', 'nullable', 'string', 'in:pg1,pg2'],
        ]);

        try {
            $data = $this->consultaNotas->listar($request->user(), $validated);
        } catch (AuthorizationException $exception) {
            return response()->json([
                'error' => $exception->getMessage() ?: 'No autorizado.',
            ], 403);
        }

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /api/admin/notas/pesos
     *
     * Update or create grade weights for a semester + tipo combination.
     * The three weights must sum to exactly 100%.
     */
    public function updatePesos(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'semestre_id' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', 'in:pg1,pg2'],
            'peso_entregas' => ['required', 'numeric', 'min:0', 'max:100'],
            'peso_evaluadores' => ['required', 'numeric', 'min:0', 'max:100'],
            'peso_presentacion' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $total = $validated['peso_entregas'] + $validated['peso_evaluadores'] + $validated['peso_presentacion'];

        if (abs($total - 100) > 0.01) {
            throw ValidationException::withMessages([
                'pesos' => sprintf(
                    'La suma de los pesos debe ser exactamente 100%% (actual: %s%%)',
                    rtrim(rtrim(number_format($total, 2, '.', ''), '0'), '.'),
                ),
            ]);
        }

        try {
            $record = CoordinadorGradeWeight::updateOrCreate(
                [
                    'semestre_id' => $validated['semestre_id'],
                    'tipo' => $validated['tipo'],
                ],
                [
                    'peso_entregas' => $validated['peso_entregas'],
                    'peso_evaluadores' => $validated['peso_evaluadores'],
                    'peso_presentacion' => $validated['peso_presentacion'],
                ]
            );
        } catch (\Throwable) {
            return response()->json([
                'error' => 'La tabla de pesos no existe. Ejecute la migración: php artisan migrate.',
            ], 500);
        }

        return response()->json([
            'data' => [
                'id' => $record->id,
                'semestre_id' => $record->semestre_id,
                'tipo' => $record->tipo,
                'peso_entregas' => (float) $record->peso_entregas,
                'peso_evaluadores' => (float) $record->peso_evaluadores,
                'peso_presentacion' => (float) $record->peso_presentacion,
            ],
        ]);
    }

    /**
     * GET /api/admin/notas/export
     *
     * Export project grades (PG1 or PG2) to an Excel spreadsheet.
     */
    public function exportar(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'semestre_id' => ['required', 'integer', 'min:1'],
            'tipo' => ['required', 'string', 'in:pg1,pg2'],
        ]);

        try {
            $data = $this->consultaNotas->listar($request->user(), $validated);
            $spreadsheet = $this->buildNotasSpreadsheet($data, $validated['tipo']);
        } catch (Throwable) {
            abort(500, self::MENSAJE_SIN_LIBRERIA);
        }

        $semestreId = (int) $validated['semestre_id'];
        $semestreNombre = collect($data['semestres'])
            ->firstWhere('id', $semestreId)['nombre'] ?? '';
        $filename = $this->buildNotasFilename($semestreNombre, $validated['tipo']);

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                $writer = new XlsxWriter($spreadsheet);
                $writer->save('php://output');
            },
            $filename,
            ['Content-Type' => self::MIME_XLSX],
        );
    }

    private function buildNotasSpreadsheet(array $data, string $tipo): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($tipo === 'pg1' ? 'Notas PG1' : 'Notas PG2');

        $esPG1 = $tipo === 'pg1';
        $tipoLabel = $esPG1 ? 'PG1' : 'PG2';

        $headers = $esPG1
            ? ['Proyecto', 'Código', 'Estudiantes', 'Director', 'Nota Entregas', 'Nota Evaluadores', 'Nota Presentación', 'Nota Final']
            : ['Proyecto', 'Código', 'Estudiantes', 'Director', 'Nota Entregas', 'Nota Evaluadores', 'Nota Director', 'Nota Final'];

        $numCols = count($headers);
        $ultimaCol = Coordinate::stringFromColumnIndex($numCols);

        // Build data rows from service response
        $rows = [];

        foreach ($data['proyectos'] as $proyecto) {
            $rows[] = $esPG1
                ? [
                    $proyecto['titulo'] ?? '',
                    $proyecto['codigo'] ?? '',
                    $proyecto['estudiantes'] ?? '',
                    $proyecto['director'] ?? '',
                    $this->formatGrade($proyecto['nota_entregas_ponderada'] ?? null),
                    $this->formatGrade($proyecto['nota_evaluadores_anteproyecto'] ?? null),
                    $this->formatGrade($proyecto['nota_presentacion_anteproyecto'] ?? null),
                    $this->formatGrade($proyecto['nota_final_pg1'] ?? null),
                ]
                : [
                    $proyecto['titulo'] ?? '',
                    $proyecto['codigo'] ?? '',
                    $proyecto['estudiantes'] ?? '',
                    $proyecto['director'] ?? '',
                    $this->formatGrade($proyecto['nota_entregas_desarrollo_ponderada'] ?? null),
                    $this->formatGrade($proyecto['nota_evaluadores_presentacion_final'] ?? null),
                    $this->formatGrade($proyecto['nota_director_presentacion_final'] ?? null),
                    $this->formatGrade($proyecto['nota_final_pg2'] ?? null),
                ];
        }

        // --- Title row (row 1) ---
        $sheet->mergeCells('A1:'.$ultimaCol.'1');
        $sheet->setCellValue('A1', 'Notas - Proyecto de Grado '.$tipoLabel);
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'C2410C']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // --- Subtitle row (row 2) ---
        $sheet->mergeCells('A2:'.$ultimaCol.'2');
        $sheet->setCellValue('A2', $data['semestres'][0]['nombre'] ?? '');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 11, 'color' => ['rgb' => '78716C']],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        // --- Header row (row 3) ---
        $sheet->fromArray([$headers], null, 'A3');
        $headerRange = 'A3:'.$ultimaCol.'3';

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => self::HEADER_TEXT_COLOR]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::HEADER_FILL]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]],
            ],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(24);

        // --- Data rows (row 4+) ---
        $fila = 4;

        foreach ($rows as $idx => $row) {
            $sheet->fromArray([$row], null, 'A'.$fila);
            $dataRange = 'A'.$fila.':'.$ultimaCol.$fila;

            $sheet->getStyle($dataRange)->applyFromArray([
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::BORDER_COLOR]],
                ],
            ]);

            // Zebra striping
            if ($idx % 2 === 1) {
                $sheet->getStyle($dataRange)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F9FAFB');
            }

            // Highlight Nota Final column (last column)
            $lastCol = Coordinate::stringFromColumnIndex($numCols);
            $sheet->getStyle($lastCol.$fila)->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::FINAL_GRADE_FILL]],
            ]);

            $fila++;
        }

        // --- Auto-size columns ---
        foreach (range(1, $numCols) as $i) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }

        return $spreadsheet;
    }

    private function formatGrade(?float $grade): string
    {
        return $grade !== null ? number_format($grade, 2) : '';
    }

    private function buildNotasFilename(string $semestreNombre, string $tipo): string
    {
        $tipoLabel = strtoupper($tipo);
        $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $semestreNombre);
        $safe = trim($safe, '-');
        $timestamp = now()->format('Y-m-d_H-i');

        return "Notas {$tipoLabel} - {$safe} - {$timestamp}.xlsx";
    }
}
