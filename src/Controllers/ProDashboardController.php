<?php

namespace Arout\SeoToolkitPro\Controllers;

use Arout\SeoToolkitPro\Analysis\InternalLinkSuggester;
use Arout\SeoToolkitPro\Crawler\RouteCrawler;
use Rhapsody\Core\Http\Request;

/**
 * NOTE: base class assumed. Core's own admin/docs/auth controllers live in
 * src/Controllers/ but no shared base class name is confirmed for
 * module-owned controllers (App-level ones extend BaseController). If
 * modules get their own base (e.g. Rhapsody\Core\Controllers\Controller),
 * extend that instead and swap $this->view()/$this->schema in for the
 * inline placeholders below.
 */
class ProDashboardController
{
    public function __construct(
        private readonly object $database,
        private readonly object $settings,
        private readonly RouteCrawler $crawler,
        private readonly InternalLinkSuggester $linkSuggester
    ) {
    }

    public function audit(): mixed
    {
        $audits = $this->database->table('mod_seo_toolkit_pro_audits')->get();
        $suggestions = $this->linkSuggester->suggest(array_map(
            fn ($row) => ['route_path' => $row['route_path'], 'keywords' => json_decode($row['keywords'] ?? '[]', true)],
            $audits
        ));

        return $this->view('admin/audit', [
            'title' => 'SEO Audit — SEO Toolkit Pro',
            'meta_description' => 'On-page audit results, readability scores, and internal linking suggestions.',
            'audits' => $audits,
            'link_suggestions' => $suggestions,
        ]);
    }

    public function runScan(): mixed
    {
        $sampleUrls = $this->database->table('mod_seo_toolkit_pro_sample_urls')->get();
        $results = $this->crawler->crawl($sampleUrls);

        foreach ($results as $row) {
            // Upsert on route_path (unique key) so re-scanning refreshes in place.
            $this->database->table('mod_seo_toolkit_pro_audits')->upsert($row, uniqueBy: 'route_path');
        }

        return $this->redirect('/seo-toolkit-pro/audit')->withFlash('success', count($results) . ' page(s) scanned.');
    }

    public function notFoundLog(): mixed
    {
        $raw = $this->database->table('mod_seo_toolkit_pro_404s')
            ->orderBy('occurred_at', 'desc')
            ->get();

        // Aggregate at query/display time per the "log everything, aggregate on read" decision.
        $aggregated = [];
        foreach ($raw as $hit) {
            $url = $hit['url'];
            $aggregated[$url] ??= ['url' => $url, 'hits' => 0, 'first_seen' => $hit['occurred_at'], 'last_seen' => $hit['occurred_at'], 'referrers' => []];
            $aggregated[$url]['hits']++;
            $aggregated[$url]['first_seen'] = min($aggregated[$url]['first_seen'], $hit['occurred_at']);
            $aggregated[$url]['last_seen'] = max($aggregated[$url]['last_seen'], $hit['occurred_at']);
            if ($hit['referrer']) {
                $aggregated[$url]['referrers'][$hit['referrer']] = true;
            }
        }

        return $this->view('admin/404s', [
            'title' => '404 Monitor — SEO Toolkit Pro',
            'meta_description' => 'Broken links and missing pages hit by real visitors.',
            'entries' => array_values($aggregated),
        ]);
    }

    public function promoteToRedirect(Request $request): mixed
    {
        $source = $request->input('source_path');
        $target = $request->input('target_path');
        $status = (int) $request->input('status_code', 301);

        $this->database->table('mod_seo_toolkit_pro_redirects')->insert([
            'source_path' => $source,
            'target_path' => $target,
            'status_code' => $status,
            'hit_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirect('/seo-toolkit-pro/redirects')->withFlash('success', 'Redirect created.');
    }

    public function redirects(): mixed
    {
        return $this->view('admin/redirects', [
            'title' => 'Redirects — SEO Toolkit Pro',
            'meta_description' => 'Manage 301/302 redirects for retired or moved URLs.',
            'redirects' => $this->database->table('mod_seo_toolkit_pro_redirects')->get(),
        ]);
    }

    public function storeRedirect(Request $request): mixed
    {
        $this->database->table('mod_seo_toolkit_pro_redirects')->insert([
            'source_path' => $request->input('source_path'),
            'target_path' => $request->input('target_path'),
            'status_code' => (int) $request->input('status_code', 301),
            'hit_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirect('/seo-toolkit-pro/redirects')->withFlash('success', 'Redirect saved.');
    }

    public function deleteRedirect(int $id): mixed
    {
        $this->database->table('mod_seo_toolkit_pro_redirects')->where('id', '=', $id)->delete();
        return $this->redirect('/seo-toolkit-pro/redirects')->withFlash('success', 'Redirect removed.');
    }

    public function sampleUrls(): mixed
    {
        return $this->view('admin/sample-urls', [
            'title' => 'Sample URLs — SEO Toolkit Pro',
            'meta_description' => 'Register concrete example URLs so parameterized routes can be audited and scored.',
            'sample_urls' => $this->database->table('mod_seo_toolkit_pro_sample_urls')->get(),
        ]);
    }

    public function storeSampleUrl(Request $request): mixed
    {
        $this->database->table('mod_seo_toolkit_pro_sample_urls')->insert([
            'route_pattern' => $request->input('route_pattern'),
            'sample_url' => $request->input('sample_url'),
            'label' => $request->input('label'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirect('/seo-toolkit-pro/sample-urls')->withFlash('success', 'Sample URL added.');
    }

    public function deleteSampleUrl(int $id): mixed
    {
        $this->database->table('mod_seo_toolkit_pro_sample_urls')->where('id', '=', $id)->delete();
        return $this->redirect('/seo-toolkit-pro/sample-urls')->withFlash('success', 'Sample URL removed.');
    }

    public function settingsForm(): mixed
    {
        return $this->view('admin/settings', [
            'title' => 'Analytics Settings — SEO Toolkit Pro',
            'meta_description' => 'Configure GA4, Meta Pixel, and custom analytics scripts.',
            'ga4_id' => $this->settings->get('analytics.ga4_id'),
            'meta_pixel_id' => $this->settings->get('analytics.meta_pixel_id'),
            'custom_script' => $this->settings->get('analytics.custom_script'),
        ]);
    }

    public function saveSettings(Request $request): mixed
    {
        $this->settings->set('analytics.ga4_id', $request->input('ga4_id'));
        $this->settings->set('analytics.meta_pixel_id', $request->input('meta_pixel_id'));
        $this->settings->set('analytics.custom_script', $request->input('custom_script'));

        return $this->redirect('/seo-toolkit-pro/settings')->withFlash('success', 'Settings saved.');
    }

    // --- Placeholders standing in for whatever base controller Rhapsody gives modules ---
    // NOTE: replace these three with the real base-controller methods once confirmed.
    private function view(string $template, array $data = []): mixed
    {
        return ['__view' => $template, '__data' => $data];
    }

    private function redirect(string $path): object
    {
        return new class ($path) {
            public function __construct(private readonly string $path)
            {
            }
            public function withFlash(string $type, string $message): self
            {
                return $this;
            }
        };
    }
}
