<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * Marked truncation for oversized markdown.
 * Without signals: keeps head 40000 + marker + tail 20000; never silently cuts.
 * With signals: keeps the highest-overlap sections (split on headings) within
 * the same budget, and the marker lists kept vs omitted sections.
 */
final class MarkdownTruncator
{
    public const MAX_CHARS = 60000;

    public const HEAD_CHARS = 40000;

    public const TAIL_CHARS = 20000;

    private const MARKER_RESERVE = 2000;

    private const MAX_SIGNALS = 40;

    /**
     * Basic Spanish stopwords removed when deriving signals from free text.
     */
    private const STOPWORDS = [
        'el', 'la', 'de', 'los', 'las', 'un', 'una', 'unos', 'unas', 'en', 'y', 'o', 'u',
        'que', 'del', 'al', 'es', 'son', 'sea', 'sean', 'para', 'por', 'con', 'se', 'su',
        'sus', 'este', 'esta', 'estos', 'estas', 'ese', 'esa', 'esos', 'esas', 'esto', 'eso',
        'como', 'más', 'mas', 'pero', 'entre', 'hasta', 'desde', 'donde', 'cuando', 'porque',
        'muy', 'sin', 'sobre', 'también', 'tambien', 'tiene', 'tienen', 'cada', 'cual', 'cuales',
        'cuál', 'cuáles', 'esto', 'aquello', 'aquel', 'aquella', 'hay', 'han', 'fue', 'fueron',
        'ser', 'estar', 'está', 'están', 'the', 'and', 'for', 'with',
    ];

    /**
     * Derive normalized signal words from free texts (e.g. acceptance
     * criteria + entrega description): lowercase, no stopwords, no
     * short tokens, deduplicated.
     *
     * @return list<string>
     */
    public static function signalsFromTexts(?string ...$texts): array
    {
        $signals = [];

        foreach ($texts as $text) {
            if ($text === null || $text === '') {
                continue;
            }

            $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            foreach ($words as $word) {
                if (mb_strlen($word) < 3 || in_array($word, self::STOPWORDS, true) || in_array($word, $signals, true)) {
                    continue;
                }

                $signals[] = $word;

                if (count($signals) >= self::MAX_SIGNALS) {
                    break 2;
                }
            }
        }

        return $signals;
    }

    /**
     * @param  list<string>  $signals  relevance words; empty keeps legacy head+tail behavior
     * @return array{text: string, wasTruncated: bool, originalChars: int, keptChars: int}
     */
    public function truncate(string $markdown, array $signals = []): array
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

        $signals = $this->normalizeSignals($signals);

        if ($signals !== []) {
            $bySections = $this->truncateBySections($markdown, $original, $signals);

            if ($bySections !== null) {
                return $bySections;
            }
        }

        $head = $this->snapHead(mb_substr($markdown, 0, self::HEAD_CHARS));
        $tail = $this->snapTail(mb_substr($markdown, -$this->tailLength($original)));
        $kept = mb_strlen($head) + mb_strlen($tail);
        $marker = "\n\n[…recortado: {$original}→{$kept}…]\n\n";

        return [
            'text' => $head.$marker.$tail,
            'wasTruncated' => true,
            'originalChars' => $original,
            'keptChars' => $kept,
        ];
    }

    /**
     * @param  list<string>  $signals
     * @return list<string>
     */
    private function normalizeSignals(array $signals): array
    {
        $normalized = [];

        foreach ($signals as $signal) {
            $word = mb_strtolower(trim((string) $signal));

            if ($word === '' || in_array($word, $normalized, true)) {
                continue;
            }

            $normalized[] = $word;
        }

        return $normalized;
    }

    /**
     * Split on markdown headings, score each section by signal-word
     * overlap, and keep the top-scoring sections within budget.
     * Returns null when sections carry no signal (caller falls back
     * to legacy head+tail) or the document has no headings.
     *
     * @param  list<string>  $signals
     * @return array{text: string, wasTruncated: bool, originalChars: int, keptChars: int}|null
     */
    private function truncateBySections(string $markdown, int $original, array $signals): ?array
    {
        $sections = $this->splitSections($markdown);

        if (count($sections) < 2) {
            return null;
        }

        $scored = [];

        foreach ($sections as $index => $section) {
            $scored[] = [
                'index' => $index,
                'title' => $section['title'],
                'body' => $section['body'],
                'score' => $this->scoreSection($section['title'].' '.$section['body'], $signals),
            ];
        }

        $best = max(array_column($scored, 'score'));

        if ($best <= 0) {
            return null;
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: $a['index'] <=> $b['index']);

        $budget = self::MAX_CHARS - self::MARKER_RESERVE;
        $kept = [];
        $used = 0;

        foreach ($scored as $candidate) {
            if ($candidate['score'] <= 0) {
                continue;
            }

            $length = mb_strlen($candidate['body']);

            if ($kept === []) {
                $kept[] = $length <= $budget
                    ? $candidate
                    : array_merge($candidate, ['body' => $this->snapHead(mb_substr($candidate['body'], 0, $budget))]);
                $used += mb_strlen($kept[0]['body']);

                continue;
            }

            if ($used + 2 + $length > $budget) {
                continue;
            }

            $kept[] = $candidate;
            $used += 2 + $length;
        }

        usort($kept, static fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        $keptIndexes = array_column($kept, 'index');
        $keptTitles = array_column($kept, 'title');
        $omittedTitles = [];

        foreach ($scored as $candidate) {
            if (! in_array($candidate['index'], $keptIndexes, true)) {
                $omittedTitles[] = $candidate['title'];
            }
        }

        $keptChars = (int) array_sum(array_map(static fn (array $s): int => mb_strlen($s['body']), $kept));
        $marker = "\n\n[…recortado por secciones: {$original}→{$keptChars}; conservadas: "
            .implode(', ', $keptTitles).'; omitidas: '.implode(', ', $omittedTitles)."…]\n\n";

        return [
            'text' => implode("\n\n", array_column($kept, 'body')).$marker,
            'wasTruncated' => true,
            'originalChars' => $original,
            'keptChars' => $keptChars,
        ];
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    private function splitSections(string $markdown): array
    {
        $parts = preg_split('/^(#{1,6}\s+.*)$/m', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $sections = [];
        $preamble = trim($parts[0] ?? '');

        if ($preamble !== '') {
            $sections[] = ['title' => 'Introduction', 'body' => $preamble];
        }

        for ($i = 1; $i < count($parts); $i += 2) {
            $heading = trim($parts[$i] ?? '');
            $body = trim($parts[$i + 1] ?? '');
            $title = trim((string) preg_replace('/^#{1,6}\s+/', '', $heading));

            if ($title === '' && $body === '') {
                continue;
            }

            $sections[] = [
                'title' => $title !== '' ? $title : 'Untitled',
                'body' => $heading.($body !== '' ? "\n\n".$body : ''),
            ];
        }

        return $sections;
    }

    /**
     * @param  list<string>  $signals
     */
    private function scoreSection(string $text, array $signals): int
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $unique = array_unique($words);
        $score = 0;

        foreach ($signals as $signal) {
            if (in_array($signal, $unique, true)) {
                $score++;
            }
        }

        return $score;
    }

    /**
     * Cut the head back to a word boundary (prefer paragraph break),
     * so words are never split mid-word.
     */
    private function snapHead(string $head): string
    {
        $cut = mb_strrpos($head, "\n\n");

        if ($cut !== false && $cut >= mb_strlen($head) - 1000) {
            return mb_substr($head, 0, $cut);
        }

        $cut = mb_strrpos($head, ' ');

        if ($cut !== false && $cut > 0) {
            return mb_substr($head, 0, $cut);
        }

        return $head;
    }

    /**
     * Advance the tail start to a word boundary (prefer paragraph break),
     * so words are never split mid-word.
     */
    private function snapTail(string $tail): string
    {
        $cut = mb_strpos($tail, "\n\n");

        if ($cut !== false && $cut <= 1000) {
            return mb_substr($tail, $cut + 2);
        }

        $cut = mb_strpos($tail, ' ');

        if ($cut !== false) {
            return mb_substr($tail, $cut + 1);
        }

        return $tail;
    }

    private function tailLength(int $original): int
    {
        return min(self::TAIL_CHARS, max(0, $original - self::HEAD_CHARS));
    }
}
