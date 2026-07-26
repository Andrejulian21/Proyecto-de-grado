<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Generic prompt text assembler — no domain templates.
 *
 * Future modules pass labeled sections; this class only formats them.
 */
final class AiPromptComposer
{
    /**
     * @param  list<array{title?: string, body: string}>  $sections
     */
    public function compose(array $sections): string
    {
        $parts = [];

        foreach ($sections as $section) {
            $body = trim((string) ($section['body'] ?? ''));
            if ($body === '') {
                continue;
            }

            $title = trim((string) ($section['title'] ?? ''));
            $parts[] = $title !== ''
                ? "## {$title}\n\n{$body}"
                : $body;
        }

        return implode("\n\n", $parts);
    }
}
