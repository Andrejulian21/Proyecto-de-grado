<?php

declare(strict_types=1);

namespace App\Services\Evaluation;

use App\Contracts\Evaluation\EvaluationAccessResolver;
use App\Contracts\Evaluation\EvaluationPromptStrategy;
use App\Contracts\Evaluation\EvaluationResultInterpreter;
use App\Enums\AiErrorCode;
use App\Enums\AiEvaluationStatus;
use App\Exceptions\AiException;
use App\Exceptions\DocumentConversionException;
use App\Exceptions\DocumentEvaluationException;
use App\Models\AiDocumentEvaluation;
use App\Models\Entrega;
use App\Models\User;
use App\Models\VersionDocumento;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPromptComposer;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Documents\DocxToMarkdownConverter;
use App\Services\Evaluation\DTO\EvaluationContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reusable orchestrator: DOCX → Markdown → prompt strategy → AiGateway → structured result.
 * Document source may be an official VersionDocumento or a temporary student upload (never persisted as a version).
 */
final class DocumentEvaluationService
{
    public function __construct(
        private readonly DocxToMarkdownConverter $docxConverter,
        private readonly AiPromptComposer $promptComposer,
        private readonly AiGateway $aiGateway,
    ) {}

    /**
     * @return array{evaluation: AiDocumentEvaluation, result: array<string, mixed>}
     *
     * @throws DocumentEvaluationException
     * @throws AiException
     */
    public function evaluate(
        User $user,
        int $entregaId,
        EvaluationPromptStrategy $strategy,
        EvaluationAccessResolver $access,
        EvaluationResultInterpreter $interpreter,
        ?int $versionId = null,
        ?UploadedFile $temporaryFile = null,
    ): array {
        $started = hrtime(true);

        $resolved = $access->resolve($user, $entregaId);
        $proyecto = $resolved['proyecto'];
        $entrega = $resolved['entrega'];

        $this->assertEntregaTieneDocumentoAnalizable($entrega);

        $tempRelativePath = null;
        $version = null;

        try {
            $documentoId = $entrega->idDocumentoAnalizableIa();

            if ($temporaryFile !== null) {
                [$absolutePath, $originalName, $documentHash, $tempRelativePath] = $this->storeTemporaryDocx(
                    $user->id,
                    $temporaryFile,
                );
                $versionDocumentoId = null;
            } else {
                $version = $this->resolveDocxVersion($entrega, $versionId);
                $this->assertVersionEsAnalizable($entrega, $version);
                $absolutePath = Storage::disk('public')->path($version->file_path);
                $documentHash = is_file($absolutePath) ? hash_file('sha256', $absolutePath) : null;
                $originalName = (string) ($version->original_name ?? basename($version->file_path));
                $versionDocumentoId = $version->id;
                $documentoId = $version->archivo_requerido_id ?: $documentoId;
            }

            $record = AiDocumentEvaluation::create([
                'user_id' => $user->id,
                'entrega_id' => $entrega->id,
                'version_documento_id' => $versionDocumentoId,
                'archivo_requerido_id' => $documentoId,
                'type' => $strategy->type(),
                'status' => AiEvaluationStatus::Pending,
                'document_hash' => $documentHash,
                'prompt_version' => $strategy->promptVersion(),
            ]);

            try {
                $markdown = $this->docxConverter->convert($absolutePath);

                $context = new EvaluationContext(
                    documentMarkdown: $markdown,
                    entregaTitle: (string) $entrega->title,
                    phase: (string) ($entrega->phase?->value ?? $entrega->phase ?? ''),
                    proyectoTitle: (string) $proyecto->title,
                    proyectoCode: (string) ($proyecto->code ?? ''),
                    description: $entrega->description,
                    originalFileName: $originalName,
                );

                $userPrompt = $this->promptComposer->compose($strategy->contextSections($context));

                $aiResponse = $this->aiGateway->complete(new AiRequest([
                    AiMessage::system($strategy->systemInstructions()),
                    AiMessage::user($userPrompt),
                ]));

                $result = $interpreter->interpret($aiResponse->content);
                $processingMs = (int) ((hrtime(true) - $started) / 1_000_000);

                $record->update([
                    'status' => AiEvaluationStatus::Completed,
                    'provider' => $aiResponse->provider,
                    'processing_ms' => $processingMs,
                    'result_json' => $result,
                    'error_code' => null,
                    'error_message' => null,
                ]);

                return ['evaluation' => $record->fresh(), 'result' => $result];
            } catch (DocumentConversionException $exception) {
                $this->markFailed($record, 'conversion_failed', $exception->getMessage(), $started);

                throw DocumentEvaluationException::invalidDocument(
                    'No fue posible procesar el documento DOCX para el análisis.',
                );
            } catch (AiException $exception) {
                $this->markFailed(
                    $record,
                    $exception->error->value,
                    $this->friendlyAiMessage($exception),
                    $started,
                    $exception->error === AiErrorCode::ProviderNotConfigured ? 'null' : null,
                );

                throw $exception;
            } catch (DocumentEvaluationException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $this->markFailed($record, 'unexpected', 'Error inesperado durante la evaluación.', $started);

                throw AiException::unexpected($exception);
            }
        } finally {
            if ($tempRelativePath !== null) {
                Storage::disk('local')->delete($tempRelativePath);
            }
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string|null, 3: string}
     */
    private function storeTemporaryDocx(int $userId, UploadedFile $file): array
    {
        $originalName = (string) ($file->getClientOriginalName() ?: 'documento.docx');

        if (! str_ends_with(strtolower($originalName), '.docx')) {
            throw DocumentEvaluationException::invalidDocument(
                'Solo se pueden analizar documentos en formato DOCX.',
            );
        }

        $relative = 'tmp/ai-eval/'.$userId.'/'.Str::uuid()->toString().'.docx';
        Storage::disk('local')->put($relative, file_get_contents($file->getRealPath()) ?: '');
        $absolute = Storage::disk('local')->path($relative);

        if (! is_file($absolute)) {
            throw DocumentEvaluationException::invalidDocument(
                'No fue posible almacenar el archivo temporal para el análisis.',
            );
        }

        return [$absolute, $originalName, hash_file('sha256', $absolute) ?: null, $relative];
    }

    private function resolveDocxVersion(Entrega $entrega, ?int $versionId): VersionDocumento
    {
        $iaId = $entrega->idDocumentoAnalizableIa();
        $query = VersionDocumento::query()->where('entrega_id', $entrega->id);

        if ($iaId !== null) {
            $query->where(function ($q) use ($entrega, $iaId) {
                $q->where('archivo_requerido_id', $iaId);

                $first = $entrega->documentosSolicitados()[0] ?? null;
                $firstId = is_array($first) ? ($first['slug'] ?? $first['id'] ?? null) : null;

                if ($firstId === $iaId) {
                    $q->orWhereNull('archivo_requerido_id');
                }
            });
        }

        if ($versionId !== null) {
            $version = VersionDocumento::query()
                ->where('entrega_id', $entrega->id)
                ->where('id', $versionId)
                ->first();

            if (! $version) {
                throw DocumentEvaluationException::notFound('No se encontró la versión del documento.');
            }
        } else {
            $version = $query->orderByDesc('version_number')->first();

            if (! $version) {
                throw DocumentEvaluationException::invalidDocument(
                    'La entrega no tiene versiones para analizar. Sube un documento DOCX primero.',
                );
            }
        }

        $name = strtolower((string) ($version->original_name ?? $version->file_path));

        if (! str_ends_with($name, '.docx')) {
            throw DocumentEvaluationException::invalidDocument(
                'Solo se pueden analizar documentos en formato DOCX.',
            );
        }

        $absolutePath = Storage::disk('public')->path($version->file_path);

        if (! is_file($absolutePath)) {
            throw DocumentEvaluationException::invalidDocument(
                'El archivo de la versión no está disponible en el almacenamiento.',
            );
        }

        return $version;
    }

    private function assertEntregaTieneDocumentoAnalizable(Entrega $entrega): void
    {
        if ($entrega->idDocumentoAnalizableIa() === null) {
            throw DocumentEvaluationException::notAnalyzable(
                'Esta entrega no tiene un documento configurado para análisis mediante IA.',
            );
        }
    }

    private function assertVersionEsAnalizable(Entrega $entrega, VersionDocumento $version): void
    {
        if (! $entrega->versionEsAnalizableIa($version)) {
            throw DocumentEvaluationException::notAnalyzable();
        }
    }

    private function markFailed(
        AiDocumentEvaluation $record,
        string $errorCode,
        string $message,
        int $startedHrtime,
        ?string $provider = null,
    ): void {
        $record->update([
            'status' => AiEvaluationStatus::Failed,
            'provider' => $provider,
            'processing_ms' => (int) ((hrtime(true) - $startedHrtime) / 1_000_000),
            'error_code' => $errorCode,
            'error_message' => $message,
        ]);
    }

    private function friendlyAiMessage(AiException $exception): string
    {
        return match ($exception->error) {
            AiErrorCode::ProviderNotConfigured,
            AiErrorCode::UnknownProvider => 'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
            default => 'No fue posible completar el análisis con el servicio de Inteligencia Artificial.',
        };
    }
}
