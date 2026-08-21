<?php

declare(strict_types=1);

namespace App\Contracts\Assistant;

use App\Enums\AiAssistantType;
use App\Services\Assistant\DTO\AssistantContext;

/**
 * Builds prompts for a specialized assistant (student orientation, future roles…).
 * Strategies never talk to AI providers directly.
 */
interface AssistantPromptStrategy
{
    public function type(): AiAssistantType;

    public function promptVersion(): string;

    public function systemInstructions(): string;

    /**
     * @return list<array{title?: string, body: string}>
     */
    public function contextSections(AssistantContext $context): array;
}
