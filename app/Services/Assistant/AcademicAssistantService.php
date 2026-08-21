<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Contracts\Assistant\AssistantPromptStrategy;
use App\Enums\AiAssistantStatus;
use App\Enums\AiErrorCode;
use App\Enums\AiMessageRole;
use App\Exceptions\AcademicAssistantException;
use App\Exceptions\AiException;
use App\Models\AiAssistantConversation;
use App\Models\AiAssistantMessage;
use App\Models\Proyecto;
use App\Models\User;
use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPromptComposer;
use App\Services\Ai\DTO\AiMessage;
use App\Services\Ai\DTO\AiRequest;
use App\Services\Assistant\DTO\AssistantContext;
use App\Services\Assistant\DTO\StructuredAssistantResult;
use Throwable;

/**
 * Reusable orchestrator: history → academic context → director catalog → strategy → AiGateway → structured result.
 * No vendor coupling. Future role assistants reuse this with a new strategy.
 */
final class AcademicAssistantService
{
    public function __construct(
        private readonly AiPromptComposer $promptComposer,
        private readonly AiGateway $aiGateway,
        private readonly AssistantResultParser $resultParser,
        private readonly DirectorCatalogBuilder $directorCatalogBuilder,
    ) {}

    /**
     * @return array{conversation: AiAssistantConversation, messages: list<AiAssistantMessage>}
     */
    public function getOrCreateConversation(User $user, AssistantPromptStrategy $strategy): array
    {
        $conversation = AiAssistantConversation::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'type' => $strategy->type(),
            ],
            [
                'status' => AiAssistantStatus::Pending,
                'prompt_version' => $strategy->promptVersion(),
            ],
        );

        $messages = $conversation->messages()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->all();

        return [
            'conversation' => $conversation->fresh(),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{
     *     conversation: AiAssistantConversation,
     *     result: StructuredAssistantResult,
     *     user_message: AiAssistantMessage,
     *     assistant_message: AiAssistantMessage
     * }
     *
     * @throws AcademicAssistantException
     * @throws AiException
     */
    public function sendMessage(User $user, string $message, AssistantPromptStrategy $strategy): array
    {
        $trimmed = trim($message);

        if ($trimmed === '') {
            throw AcademicAssistantException::invalidMessage('El mensaje no puede estar vacío.');
        }

        if (mb_strlen($trimmed) > 4000) {
            throw AcademicAssistantException::invalidMessage('El mensaje supera el límite de 4000 caracteres.');
        }

        $started = hrtime(true);
        $bundle = $this->getOrCreateConversation($user, $strategy);
        /** @var AiAssistantConversation $conversation */
        $conversation = $bundle['conversation'];

        $conversation->update([
            'status' => AiAssistantStatus::Pending,
            'prompt_version' => $strategy->promptVersion(),
            'error_code' => null,
            'error_message' => null,
        ]);

        $userMessage = AiAssistantMessage::create([
            'conversation_id' => $conversation->id,
            'role' => AiMessageRole::User,
            'content' => $trimmed,
        ]);

        try {
            $directors = $this->directorCatalogBuilder->build();
            $history = $this->historyForContext($conversation, $userMessage->id);
            $proyecto = $this->resolveStudentProjectSummary($user);

            $context = new AssistantContext(
                studentName: (string) $user->name,
                studentEmail: (string) $user->email,
                studentCode: $user->codigo_estudiante !== null ? (string) $user->codigo_estudiante : null,
                proyecto: $proyecto,
                history: $history,
                directors: $directors,
                userMessage: $trimmed,
            );

            $userPrompt = $this->promptComposer->compose($strategy->contextSections($context));

            $aiResponse = $this->aiGateway->complete(new AiRequest([
                AiMessage::system($strategy->systemInstructions()),
                AiMessage::user($userPrompt),
            ]));

            $result = $this->resultParser->parse($aiResponse->content, $directors);
            $processingMs = (int) ((hrtime(true) - $started) / 1_000_000);

            $assistantMessage = AiAssistantMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiMessageRole::Assistant,
                'content' => $result->mensaje !== ''
                    ? $result->mensaje
                    : 'He actualizado la orientación de tu proyecto de grado.',
                'structured_json' => $result->toArray(),
            ]);

            $conversation->update([
                'status' => AiAssistantStatus::Completed,
                'provider' => $aiResponse->provider,
                'processing_ms' => $processingMs,
                'result_json' => $result->toArray(),
                'error_code' => null,
                'error_message' => null,
            ]);

            return [
                'conversation' => $conversation->fresh(),
                'result' => $result,
                'user_message' => $userMessage,
                'assistant_message' => $assistantMessage,
            ];
        } catch (AiException $exception) {
            $this->markFailed(
                $conversation,
                $exception->error->value,
                $this->friendlyAiMessage($exception),
                $started,
                $exception->error === AiErrorCode::ProviderNotConfigured ? 'null' : null,
            );

            throw $exception;
        } catch (AcademicAssistantException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            $this->markFailed($conversation, 'unexpected', 'Error inesperado durante la orientación.', $started);

            throw AiException::unexpected($exception);
        }
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private function historyForContext(AiAssistantConversation $conversation, int $excludeMessageId): array
    {
        return $conversation->messages()
            ->where('id', '!=', $excludeMessageId)
            ->whereIn('role', [AiMessageRole::User->value, AiMessageRole::Assistant->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (AiAssistantMessage $message): array => [
                'role' => $message->role->value,
                'content' => (string) $message->content,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveStudentProjectSummary(User $user): ?array
    {
        $proyecto = Proyecto::query()
            ->whereHas('estudiantes', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->first();

        if (! $proyecto) {
            return null;
        }

        return [
            'id' => $proyecto->id,
            'code' => (string) ($proyecto->code ?? ''),
            'title' => (string) $proyecto->title,
            'phase' => (string) ($proyecto->current_phase?->value ?? $proyecto->current_phase ?? ''),
            'status' => (string) ($proyecto->status?->value ?? $proyecto->status ?? ''),
            'director_id' => $proyecto->director_id,
        ];
    }

    private function markFailed(
        AiAssistantConversation $conversation,
        string $errorCode,
        string $message,
        int $startedHrtime,
        ?string $provider = null,
    ): void {
        $conversation->update([
            'status' => AiAssistantStatus::Failed,
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
            default => 'No fue posible completar la orientación con el servicio de Inteligencia Artificial.',
        };
    }
}
