<?php

namespace Arout\SeoToolkitPro;

use Arout\SeoToolkitPro\Analysis\InternalLinkSuggester;
use Arout\SeoToolkitPro\Analysis\PageAuditor;
use Arout\SeoToolkitPro\Analysis\ReadabilityScorer;
use Arout\SeoToolkitPro\Controllers\ProDashboardController;
use Arout\SeoToolkitPro\Crawler\RouteCrawler;
use Arout\SeoToolkitPro\Listeners\RouteNotFoundListener;
use Arout\SeoToolkitPro\Twig\AnalyticsTwigFunctions;
use Rhapsody\Core\Events\RouteNotFound;

/**
 * NOTE: The exact ModuleProvider contract (register() vs boot(), what a
 * ModuleContext exposes beyond the facade names already confirmed in
 * seo-toolkit's build — events(), routes(), database(), settings(),
 * twig()) is assumed here from those confirmed facades. Adjust method
 * names to match the real ModuleContext signature if they differ.
 */
class ModuleProvider
{
    public function register(\Rhapsody\Core\Contracts\ModuleContext $context): void
    {
        // --- 404 monitor + redirect manager ---
        // A single RouteNotFound listener owns both: it checks the redirect
        // table first (and short-circuits with a Response if it matches),
        // then falls back to logging the raw 404 hit.
        $context->events()->listen(
            RouteNotFound::class,
            new RouteNotFoundListener($context->database())
        );

        // --- Admin dashboard routes ---
        // All auto-prefixed under this module's slug, e.g.
        // /seo-toolkit-pro/audit, /seo-toolkit-pro/404s, etc.
        $dashboard = new ProDashboardController(
            $context->database(),
            $context->settings(),
            new RouteCrawler($context->routes(), new PageAuditor(), new ReadabilityScorer()),
            new InternalLinkSuggester()
        );

        $context->routes()->get('/audit', [$dashboard, 'audit']);
        $context->routes()->post('/audit/scan', [$dashboard, 'runScan']);
        $context->routes()->get('/404s', [$dashboard, 'notFoundLog']);
        $context->routes()->post('/404s/promote', [$dashboard, 'promoteToRedirect']);
        $context->routes()->get('/redirects', [$dashboard, 'redirects']);
        $context->routes()->post('/redirects', [$dashboard, 'storeRedirect']);
        $context->routes()->post('/redirects/{id}/delete', [$dashboard, 'deleteRedirect']);
        $context->routes()->get('/sample-urls', [$dashboard, 'sampleUrls']);
        $context->routes()->post('/sample-urls', [$dashboard, 'storeSampleUrl']);
        $context->routes()->post('/sample-urls/{id}/delete', [$dashboard, 'deleteSampleUrl']);
        $context->routes()->get('/settings', [$dashboard, 'settingsForm']);
        $context->routes()->post('/settings', [$dashboard, 'saveSettings']);

        // --- Analytics tag manager ---
        // Exposed as a Twig function so theme authors opt in explicitly,
        // same pattern as the {{ schema_markup|raw }} convention.
        $context->twig()->functions([
            'seo_analytics_scripts' => new AnalyticsTwigFunctions($context->settings()),
        ]);
    }
}
