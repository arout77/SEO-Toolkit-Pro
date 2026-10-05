<?php

namespace Arout\SeoToolkitPro\Controllers;

use Arout\SeoToolkitPro\Analysis\InternalLinkSuggester;
use Arout\SeoToolkitPro\Crawler\RouteCrawler;
use Rhapsody\Core\Http\Request;
use Rhapsody\Core\Modules\Facades\DatabaseFacade;
use Rhapsody\Core\Modules\Facades\TwigFacade;
use Rhapsody\Core\Response;

/**
 * NOTE on the two things in here still not confirmed against real source:
 *
 * 1. redirect() — assumed to be a global helper function, `redirect(string
 *    $path): Response`, matching the common convention this style of
 *    framework usually ships. If Core actually exposes this differently
 *    (a Response::redirect() static, a facade, etc.), every call site
 *    below needs the same one-line swap.
 *
 * 2. Flash messages — rather than guess an unconfirmed Session::flash()
 *    helper's signature, this sets $_SESSION['flash_success'] /
 *    $_SESSION['flash_error'] directly, matching the exact keys already
 *    confirmed live in BaseController.php. Functionally this should behave
 *    identically to whatever Session::flash() does internally, assuming
 *    it's a thin wrapper over the same two keys.
 *
 * 3. Redirect target paths are hardcoded to the current vendor-joined
 *    route prefix (`/arout-seo-toolkit-pro/...`) since that's what's
 *    actually registered until the Phase 7 RoutesFacade slug fix lands.
 *    These all need updating to `/seo-toolkit-pro/...` at that point —
 *    there's no confirmed "build a URL for my own route" helper to avoid
 *    hardcoding this.
 */
class ProDashboardController
{
    private const PREFIX = '/arout-seo-toolkit-pro';

    public function __construct(
        private readonly DatabaseFacade $database,
        private readonly object $settings,
        private readonly TwigFacade $twig,
        private readonly RouteCrawler $crawler,
        private readonly InternalLinkSuggester $linkSuggester
    ) {
    }

    public function audit(): Response
    {
        $audits = $this->database->select('mod_arout_seo_toolkit_pro_audits');
        usort($audits, fn ($a, $b) => strcmp($a['route_path'], $b['route_path']));

        $suggestions = $this->linkSuggester->suggest(array_map(
            fn ($row) => ['route_path' => $row['route_path'], 'keywords' => json_decode($row['keywords'] ?? '[]', true)],
            $audits
        ));

        return $this->view('admin/audit.twig', [
            'title' => 'SEO Audit — SEO Toolkit Pro',
            'meta_description' => 'On-page audit results, readability scores, and internal linking suggestions.',
            'audits' => $audits,
            'link_suggestions' => $suggestions,
        ]);
    }

    public function runScan(): Response
    {
        $sampleUrls = $this->database->select('mod_arout_seo_toolkit_pro_sample_urls');
        $results = $this->crawler->crawl($sampleUrls);

        foreach ($results as $row) {
            $existing = $this->database->select('mod_arout_seo_toolkit_pro_audits', ['route_path' => $row['route_path']]);

            if ($existing) {
                $this->database->update('mod_arout_seo_toolkit_pro_audits', $row, ['id' => $existing[0]['id']]);
            } else {
                $this->database->insert('mod_arout_seo_toolkit_pro_audits', $row);
            }
        }

        return $this->redirectWithFlash(self::PREFIX . '/audit', 'success', count($results) . ' page(s) scanned.');
    }

    public function notFoundLog(): Response
    {
        $raw = $this->database->query('SELECT * FROM `mod_arout_seo_toolkit_pro_404s` ORDER BY occurred_at DESC');

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

        return $this->view('admin/404s.twig', [
            'title' => '404 Monitor — SEO Toolkit Pro',
            'meta_description' => 'Broken links and missing pages hit by real visitors.',
            'entries' => array_values($aggregated),
        ]);
    }

    public function promoteToRedirect(Request $request): Response
    {
        $this->database->insert('mod_arout_seo_toolkit_pro_redirects', [
            'source_path' => $request->input('source_path'),
            'target_path' => $request->input('target_path'),
            'status_code' => (int) $request->input('status_code', 301),
            'hit_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirectWithFlash(self::PREFIX . '/redirects', 'success', 'Redirect created.');
    }

    public function redirects(): Response
    {
        return $this->view('admin/redirects.twig', [
            'title' => 'Redirects — SEO Toolkit Pro',
            'meta_description' => 'Manage 301/302 redirects for retired or moved URLs.',
            'redirects' => $this->database->select('mod_arout_seo_toolkit_pro_redirects'),
        ]);
    }

    public function storeRedirect(Request $request): Response
    {
        $this->database->insert('mod_arout_seo_toolkit_pro_redirects', [
            'source_path' => $request->input('source_path'),
            'target_path' => $request->input('target_path'),
            'status_code' => (int) $request->input('status_code', 301),
            'hit_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirectWithFlash(self::PREFIX . '/redirects', 'success', 'Redirect saved.');
    }

    public function deleteRedirect(int $id): Response
    {
        $this->database->delete('mod_arout_seo_toolkit_pro_redirects', ['id' => $id]);
        return $this->redirectWithFlash(self::PREFIX . '/redirects', 'success', 'Redirect removed.');
    }

    public function sampleUrls(): Response
    {
        return $this->view('admin/sample-urls.twig', [
            'title' => 'Sample URLs — SEO Toolkit Pro',
            'meta_description' => 'Register concrete example URLs so parameterized routes can be audited and scored.',
            'sample_urls' => $this->database->select('mod_arout_seo_toolkit_pro_sample_urls'),
        ]);
    }

    public function storeSampleUrl(Request $request): Response
    {
        $this->database->insert('mod_arout_seo_toolkit_pro_sample_urls', [
            'route_pattern' => $request->input('route_pattern'),
            'sample_url' => $request->input('sample_url'),
            'label' => $request->input('label'),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->redirectWithFlash(self::PREFIX . '/sample-urls', 'success', 'Sample URL added.');
    }

    public function deleteSampleUrl(int $id): Response
    {
        $this->database->delete('mod_arout_seo_toolkit_pro_sample_urls', ['id' => $id]);
        return $this->redirectWithFlash(self::PREFIX . '/sample-urls', 'success', 'Sample URL removed.');
    }

    public function settingsForm(): Response
    {
        return $this->view('admin/settings.twig', [
            'title' => 'Analytics Settings — SEO Toolkit Pro',
            'meta_description' => 'Configure GA4, Meta Pixel, and custom analytics scripts.',
            'ga4_id' => $this->settings->get('analytics.ga4_id'),
            'meta_pixel_id' => $this->settings->get('analytics.meta_pixel_id'),
            'custom_script' => $this->settings->get('analytics.custom_script'),
        ]);
    }

    public function saveSettings(Request $request): Response
    {
        $this->settings->set('analytics.ga4_id', $request->input('ga4_id'));
        $this->settings->set('analytics.meta_pixel_id', $request->input('meta_pixel_id'));
        $this->settings->set('analytics.custom_script', $request->input('custom_script'));

        return $this->redirectWithFlash(self::PREFIX . '/settings', 'success', 'Settings saved.');
    }

    // --- Real implementations, replacing the old array/stub placeholders ---

    /**
     * main.twig's default title/description blocks render {{ meta.title }}/
     * {{ meta.description }} (confirmed from the theme-submission
     * requirements) — BaseController's view() apparently supplies this via
     * a third argument, but TwigFacade::render() doesn't do that wrapping
     * yet (Phase 5 territory), so it's done here instead, pulled from the
     * same 'title'/'meta_description' keys every call site already passes.
     */
    private function view(string $template, array $data = []): Response
    {
        $data['meta'] ??= [
            'title' => $data['title'] ?? null,
            'description' => $data['meta_description'] ?? null,
        ];

        return $this->twig->render($template, $data);
    }

    private function redirectWithFlash(string $path, string $type, string $message): Response
    {
        $_SESSION['flash_' . $type] = $message;
        return redirect($path);
    }
}
