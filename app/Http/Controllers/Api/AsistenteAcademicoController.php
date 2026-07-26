<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\AiErrorCode;
use App\Exceptions\AcademicAssistantException;
use App\Exceptions\AiException;
use App\Http\Controllers\Controller;
use App\Models\AiAssistantMessage;
use App\Services\Assistant\AcademicAssistantService;
use App\Services\Assistant\Strategies\StudentOrientationStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Student-facing academic orientation assistant.
 * Delegates to AcademicAssistantService; never talks to AI vendors.
 */
class AsistenteAcademicoController extends Controller
{
    public function __construct(
        private readonly AcademicAssistantService $assistantService,
        private readonly StudentOrientationStrategy $strategy,
    ) {}

    /**
     * GET /api/estudiante/asistente/conversacion
     */
    public function show(Request $request): JsonResponse
    {
        $bundle = $this->assistantService->getOrCreateConversation($request->user(), $this->strategy);
        $conversation = $bundle['conversation'];

        return response()->json([
            'data' => [
                'id' => $conversation->id,
                'tipo' => $conversation->type->value,
                'estado' => $conversation->status->value,
                'proveedor' => $conversation->provider,
                'tiempo_ms' => $conversation->processing_ms,
                'resultado' => $conversation->result_json,
                'mensajes' => array_map(
                    fn (AiAssistantMessage $message): array => $this->mapMessage($message),
                    $bundle['messages'],
                ),
            ],
        ]);
    }

    /**
     * POST /api/estudiante/asistente/mensajes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'mensaje' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $outcome = $this->assistantService->sendMessage(
                user: $request->user(),
                message: (string) $validated['mensaje'],
                strategy: $this->strategy,
            );

            $conversation = $outcome['conversation'];
            $result = $outcome['result'];

            return response()->json([
                'data' => [
                    'conversacion_id' => $conversation->id,
                    'tipo' => $conversation->type->value,
                    'estado' => $conversation->status->value,
                    'proveedor' => $conversation->provider,
                    'tiempo_ms' => $conversation->processing_ms,
                    'mensaje_usuario' => $this->mapMessage($outcome['user_message']),
                    'mensaje_asistente' => $this->mapMessage($outcome['assistant_message']),
                    'resultado' => $result->toArray(),
                ],
            ]);
        } catch (AcademicAssistantException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        } catch (AiException $exception) {
            if (in_array($exception->error, [
                AiErrorCode::ProviderNotConfigured,
                AiErrorCode::UnknownProvider,
            ], true)) {
                return response()->json([
                    'error' => 'No fue posible conectarse al servicio de Inteligencia Artificial. Inténtalo más tarde.',
                    'code' => 'ai_unavailable',
                ], 503);
            }

            return response()->json([
                'error' => 'No fue posible completar la orientación con el servicio de Inteligencia Artificial.',
                'code' => $exception->error->value,
            ], 502);
        }
    }

    /**
     * @return array{id: int, role: string, content: string, structured: array<string, mixed>|null, created_at: string|null}
     */
    private function mapMessage(AiAssistantMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role->value,
            'content' => (string) $message->content,
            'structured' => $message->structured_json,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
