<?php

declare(strict_types=1);

namespace App\Services\Ai\DTO;

use App\Enums\AiMessageRole;

/**
 * Single provider-agnostic message in an AI conversation/completion.
 */
final readonly class AiMessage
{
    public function __construct(
        public AiMessageRole $role,
        public string $content,
    ) {}

    public static function system(string $content): self
    {
        return new self(AiMessageRole::System, $content);
    }

    public static function user(string $content): self
    {
        return new self(AiMessageRole::User, $content);
    }

    public static function assistant(string $content): self
    {
        return new self(AiMessageRole::Assistant, $content);
    }
}
