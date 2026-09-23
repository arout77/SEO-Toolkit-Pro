<?php

namespace Arout\SeoToolkitPro\Analysis;

/** Common English stopwords excluded when extracting keywords for the internal linking suggester. */
class PageAuditor
{
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'but', 'is', 'are', 'was', 'were', 'to',
        'of', 'in', 'on', 'for', 'with', 'at', 'by', 'from', 'as', 'this',
        'that', 'it', 'be', 'your', 'you', 'we', 'our', 'us',
    ];

    /**
     * @return array{
     *   title: ?string, meta_description: ?string, h1_count: int,
     *   images_total: int, images_missing_alt: int, has_canonical: bool,
     *   body_text: string, keywords: array<int,string>
     * }
     */
    public function analyze(string $html): array
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);

        $title = $doc->getElementsByTagName('title')->item(0)?->textContent;

        $metaDescription = null;
        foreach ($doc->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name')) === 'description') {
                $metaDescription = $meta->getAttribute('content');
                break;
            }
        }

        $h1Count = $doc->getElementsByTagName('h1')->length;

        $images = $doc->getElementsByTagName('img');
        $imagesTotal = $images->length;
        $imagesMissingAlt = 0;
        foreach ($images as $img) {
            if (trim($img->getAttribute('alt')) === '') {
                $imagesMissingAlt++;
            }
        }

        $hasCanonical = $xpath->query("//link[@rel='canonical']")->length > 0;

        $bodyNode = $doc->getElementsByTagName('body')->item(0);
        $bodyText = $bodyNode ? trim(preg_replace('/\s+/', ' ', $bodyNode->textContent)) : '';

        return [
            'title' => $title !== null ? trim($title) : null,
            'meta_description' => $metaDescription !== null ? trim($metaDescription) : null,
            'h1_count' => $h1Count,
            'images_total' => $imagesTotal,
            'images_missing_alt' => $imagesMissingAlt,
            'has_canonical' => $hasCanonical,
            'body_text' => $bodyText,
            'keywords' => $this->extractKeywords(($title ?? '') . ' ' . ($metaDescription ?? '')),
        ];
    }

    /** @return array<int,string> up to 8 lowercase keywords, longest-first, stopwords removed */
    private function extractKeywords(string $text): array
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', strtolower($text)) ?: [];
        $words = array_filter($words, fn ($w) => strlen($w) > 2 && !in_array($w, self::STOPWORDS, true));

        $counts = array_count_values($words);
        arsort($counts);

        return array_slice(array_keys($counts), 0, 8);
    }
}
