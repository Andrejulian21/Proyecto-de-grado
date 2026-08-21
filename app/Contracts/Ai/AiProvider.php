<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Services\Ai\DTO\AiRequest;
use App\Services\Ai\DTO\AiResponse;

/**
 * Contract for AI providers (OpenAI, Gemini, FastAPI bridge, etc.).
 *
 * New providers implement this interface and register in config/ai.php —
 * no changes required in AiGateway or consumer modules.
 */
interface AiProvider
{
    /**
     * Stable provider key (e.g. "null", "fastapi", "openai").
     */
    public function name(): string;

    /**
     * Execute a completion/chat-style request.
     */
    public function complete(AiRequest $request): AiResponse;
}
