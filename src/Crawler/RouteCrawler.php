<?php

namespace Arout\SeoToolkitPro\Crawler;

use Arout\SeoToolkitPro\Analysis\PageAuditor;
use Arout\SeoToolkitPro\Analysis\ReadabilityScorer;
use Rhapsody\Core\Http\Request;

/**
 * NOTE: This class assumes:
 *   - $routesFacade->registered() returns an array of route definitions,
 *     each with at least ['method' => 'GET', 'path' => '/products/{slug}'].
 *     (Reasonable given the dynamic sitemap generator in Core reads the same
 *     route table off web.php.)
 *   - Rhapsody\Core\Http\Request has a static factory for building a synthetic
 *     GET request, and the Router can be resolved from the container and
 *     dispatched directly without going over real HTTP.
 * Both are unconfirmed against the actual Router/Request source — if the
 * real signatures differ, only the two marked lines below need to change.
 */
class RouteCrawler
{
    /** Paths containing any of these segments are never auto-crawled. */
    private array $skipContains = ['/admin', '/seo-toolkit-pro', '/api/'];

    public function __construct(
        private readonly object $routes,
        private readonly PageAuditor $auditor,
        private readonly ReadabilityScorer $readability
    ) {
    }

    /**
     * @param array<int,array{route_pattern:string,sample_url:string}> $sampleUrls
     * @return array<int,array<string,mixed>> one audit row per crawled page
     */
    public function crawl(array $sampleUrls = []): array
    {
        $targets = $this->staticTargets();

        foreach ($sampleUrls as $sample) {
            $targets[] = [
                'path' => $sample['sample_url'],
                'source_type' => 'sample_url',
                'route_pattern' => $sample['route_pattern'],
            ];
        }

        $results = [];
        foreach ($targets as $target) {
            $html = $this->render($target['path']);
            if ($html === null) {
                continue; // route errored (e.g. requires auth) — skip rather than fail the whole scan
            }

            $audit = $this->auditor->analyze($html);
            $readability = $this->readability->score($audit['body_text']);

            $results[] = [
                'route_path' => $target['path'],
                'source_type' => $target['source_type'],
                'route_pattern' => $target['route_pattern'],
                'title' => $audit['title'],
                'title_length' => mb_strlen($audit['title'] ?? ''),
                'meta_description' => $audit['meta_description'],
                'meta_description_length' => mb_strlen($audit['meta_description'] ?? ''),
                'h1_count' => $audit['h1_count'],
                'images_total' => $audit['images_total'],
                'images_missing_alt' => $audit['images_missing_alt'],
                'has_canonical' => $audit['has_canonical'],
                'word_count' => $readability['word_count'],
                'readability_score' => $readability['score'],
                'readability_label' => $readability['label'],
                'keywords' => json_encode($audit['keywords']),
                'scanned_at' => date('Y-m-d H:i:s'),
            ];
        }

        return $results;
    }

    /** @return array<int,array{path:string,source_type:string,route_pattern:?string}> */
    private function staticTargets(): array
    {
        $targets = [];

        foreach ($this->routes->registered() as $route) {
            if (($route['method'] ?? 'GET') !== 'GET') {
                continue;
            }
            if (str_contains($route['path'], '{')) {
                continue; // parameterized — only reachable via a registered sample URL
            }
            if ($this->shouldSkip($route['path'])) {
                continue;
            }

            $targets[] = [
                'path' => $route['path'],
                'source_type' => 'static',
                'route_pattern' => null,
            ];
        }

        return $targets;
    }

    private function shouldSkip(string $path): bool
    {
        foreach ($this->skipContains as $needle) {
            if (str_contains($path, $needle)) {
                return true;
            }
        }
        return false;
    }

    private function render(string $path): ?string
    {
        try {
            // ASSUMED API — confirm against the real Router/Request. In
            // particular `app()` as a global container-resolve helper isn't
            // confirmed; swap for however ModuleContext exposes the
            // container/Router (likely $this->routes or a dedicated method).
            $request = Request::createInternal('GET', $path);
            $response = app(\Rhapsody\Core\Routing\Router::class)->dispatch($request);

            if ($response->getStatusCode() >= 400) {
                return null;
            }

            return (string) $response->getBody();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
