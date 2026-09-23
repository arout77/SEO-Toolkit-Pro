<?php

namespace Arout\SeoToolkitPro\Analysis;

class ReadabilityScorer
{
    /**
     * @return array{score: ?float, label: ?string, word_count: int}
     */
    public function score(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return ['score' => null, 'label' => null, 'word_count' => 0];
        }

        $sentences = max(1, preg_match_all('/[.!?]+/', $text));
        $words = preg_split('/\s+/', $text) ?: [];
        $wordCount = count($words);

        if ($wordCount === 0) {
            return ['score' => null, 'label' => null, 'word_count' => 0];
        }

        $syllableCount = array_sum(array_map([$this, 'countSyllables'], $words));

        // Flesch Reading Ease
        $score = 206.835
            - 1.015 * ($wordCount / $sentences)
            - 84.6 * ($syllableCount / $wordCount);

        $score = round(max(0, min(100, $score)), 1);

        return [
            'score' => $score,
            'label' => $this->label($score),
            'word_count' => $wordCount,
        ];
    }

    private function label(float $score): string
    {
        return match (true) {
            $score >= 90 => 'Very easy',
            $score >= 70 => 'Easy',
            $score >= 60 => 'Standard',
            $score >= 50 => 'Fairly difficult',
            $score >= 30 => 'Difficult',
            default => 'Very difficult',
        };
    }

    /** Heuristic vowel-group syllable count — not linguistically exact, but stable enough for a relative score. */
    private function countSyllables(string $word): int
    {
        $word = strtolower(preg_replace('/[^a-z]/i', '', $word));
        if ($word === '') {
            return 0;
        }

        if (str_ends_with($word, 'e') && !str_ends_with($word, 'le')) {
            $word = substr($word, 0, -1);
        }

        preg_match_all('/[aeiouy]+/', $word, $matches);
        return max(1, count($matches[0]));
    }
}
