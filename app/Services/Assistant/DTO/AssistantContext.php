<?php

declare(strict_types=1);

namespace App\Services\Assistant\DTO;

/**
 * Domain context assembled for an assistant turn (provider-agnostic).
 *
 * @phpstan-type HistoryMessage array{role: string, content: string}
 * @phpstan-type DirectorEntry array<string, mixed>
 */
final readonly class AssistantContext
{
    /**
     * @param  list<array{role: string, content: string}>  $history
     * @param  list<array<string, mixed>>  $directors
     * @param  array<string, mixed>|null  $proyecto
     */
    public function __construct(
        public string $studentName,
        public string $studentEmail,
        public ?string $studentCode,
        public ?array $proyecto,
        public array $history,
        public array $directors,
        public string $userMessage,
    ) {}
}
