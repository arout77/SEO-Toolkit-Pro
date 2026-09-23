<?php

namespace Arout\SeoToolkitPro;

use Arout\SeoToolkitPro\Analysis\InternalLinkSuggester;
use Arout\SeoToolkitPro\Analysis\PageAuditor;
use Arout\SeoToolkitPro\Analysis\ReadabilityScorer;
use Arout\SeoToolkitPro\Controllers\ProDashboardController;
use Arout\SeoToolkitPro\Crawler\RouteCrawler;
use Arout\SeoToolkitPro\Listeners\RouteNotFoundListener;
use Arout\SeoToolkitPro\Twig\AnalyticsTwigFunctions;
use Rhapsody\Core\Contracts\ModuleContext;
use Rhapsody\Core\Events\RouteNotFound;
use Rhapsody\Core\Modules\Contracts\ModuleServiceProviderInterface;

class ModuleProvider implements ModuleServiceProviderInterface
{
    /**
     * NOTE: register() vs boot() split is still a guess — I don't have the
     * interface's doc comments or Core's own ModuleProvider to compare
     * against. My assumption: register() should be safe to run in
     * isolation (no dependency on other modules being wired yet), boot()
     * runs once every module has been registered. If routes/events/twig
     * functions actually need to be in register() instead (or it turns out
     * order doesn't matter and there's only one meaningful phase), moving
     * this block between the two methods is the only likely fix.
     */
    public function register(ModuleContext $context): void
    {
        // No cross-module bindings needed yet — everything below depends
        // only on this module's own facades, so it's all in boot() for now.
    }

    public function boot(ModuleContext $context): void
    {
        // --- 404 monitor + redirect manager ---
        $context->events()->listen(
            RouteNotFound::class,
            new RouteNotFoundListener($context->database())
        );

        // --- Admin dashboard routes ---
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
        $context->twig()->functions([
            'seo_analytics_scripts' => new AnalyticsTwigFunctions($context->settings()),
        ]);
    }

    /**
     * Runs once, on `module:install`. This is the real answer to the
     * migration-delivery question the README flagged as unconfirmed —
     * there's a dedicated lifecycle hook for it rather than a generic
     * migration-file glob, so the schema just runs straight from here.
     *
     * NOTE: $context->database()->raw($sql) is a guess at how to execute
     * an arbitrary multi-statement SQL file through DatabaseFacade — the
     * "scoped CRUD + Doctrine" description doesn't confirm a raw-execute
     * method exists, or what it's called if so. If this errors, that's
     * the one line to fix; the SQL file itself doesn't need to change.
     */
    public function install(ModuleContext $context): void
    {
        $sql = file_get_contents(__DIR__ . '/../database/migrations/2026_09_24_000001_create_seo_toolkit_pro_tables.sql');

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $context->database()->raw($statement);
        }
    }

    /**
     * Runs once, on `module:uninstall`. Drops this module's own tables —
     * never touches arout/seo-toolkit's tables, only mod_seo_toolkit_pro_*.
     */
    public function uninstall(ModuleContext $context): void
    {
        foreach (['audits', '404s', 'redirects', 'sample_urls'] as $table) {
            $context->database()->raw("DROP TABLE IF EXISTS mod_seo_toolkit_pro_{$table}");
        }
    }
}
