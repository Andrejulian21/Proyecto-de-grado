<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * Marked head+tail truncation for oversized markdown.
 * Keeps head 40000 + marker + tail 20000; never silently cuts.
 */
final class MarkdownTruncator
{
    public const MAX_CHARS = 60000;

    public const HEAD_CHARS = 40000;

    public const TAIL_CHARS = 20000;

    /**
     * @return array{text: string, wasTruncated: bool, originalChars: int, keptChars: int}
     */
    public function truncate(string $markdown): array
    {
        $original = mb_strlen($markdown);

        if ($original <= self::MAX_CHARS) {
            return [
                'text' => $markdown,
                'wasTruncated' => false,
                'originalChars' => $original,
                'keptChars' => $original,
            ];
        }

        $head = mb_substr($markdown, 0, self::HEAD_CHARS);
        $tail = mb_substr($markdown, -$this->tailLength($original));
        $kept = self::HEAD_CHARS + self::TAIL_CHARS;
        $marker = "\n\n[…recortado: {$original}→{$kept}…]\n\n";

        return [
            'text' => $head.$marker.$tail,
            'wasTruncated' => true,
            'originalChars' => $original,
            'keptChars' => $kept,
        ];
    }

    private function tailLength(int $original): int
    {
        return min(self::TAIL_CHARS, max(0, $original - self::HEAD_CHARS));
    }
}
