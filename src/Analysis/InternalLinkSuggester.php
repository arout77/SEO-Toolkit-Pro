<?php

namespace Arout\SeoToolkitPro\Analysis;

/**
 * Deliberately the simple version: keyword/tag overlap between pages'
 * title + meta description, not full content similarity. Rhapsody has no
 * content/tagging model yet for a stronger version to build on — full
 * content-similarity linking is tracked on the roadmap as a future upgrade.
 */
class InternalLinkSuggester
{
    public function __construct(private readonly int $minSharedKeywords = 2)
    {
    }

    /**
     * @param array<int,array{route_path:string,keywords:array<int,string>}> $pages
     * @return array<int,array{from:string,to:string,shared_keywords:array<int,string>}>
     */
    public function suggest(array $pages): array
    {
        $suggestions = [];
        $count = count($pages);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $shared = array_values(array_intersect(
                    $pages[$i]['keywords'],
                    $pages[$j]['keywords']
                ));

                if (count($shared) >= $this->minSharedKeywords) {
                    $suggestions[] = [
                        'from' => $pages[$i]['route_path'],
                        'to' => $pages[$j]['route_path'],
                        'shared_keywords' => $shared,
                    ];
                }
            }
        }

        return $suggestions;
    }
}
