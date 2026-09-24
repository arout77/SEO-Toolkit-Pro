<?php

namespace Arout\SeoToolkitPro;

use Arout\SeoToolkitPro\Analysis\InternalLinkSuggester;
use Arout\SeoToolkitPro\Analysis\PageAuditor;
use Arout\SeoToolkitPro\Analysis\ReadabilityScorer;
use Arout\SeoToolkitPro\Controllers\ProDashboardController;
use Arout\SeoToolkitPro\Crawler\RouteCrawler;
use Arout\SeoToolkitPro\Listeners\RouteNotFoundListener;
use Arout\SeoToolkitPro\Twig\AnalyticsTwigFunctions;
use Rhapsody\Core\Modules\ModuleContext;
use Rhapsody\Core\Events\RouteNotFound;
use Rhapsody\Core\Modules\Contracts\ModuleServiceProviderInterface;

class ModuleProvider implements ModuleServiceProviderInterface
{
    public function register(ModuleContext $context): void
    {
        // No cross-module bindings needed — everything below depends only
        // on this module's own facades, so it all lives in boot().
    }

    public function boot(ModuleContext $context): void
    {
        $context->events()->listen(
            RouteNotFound::class,
            new RouteNotFoundListener($context->database())
        );

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

        $context->twig()->functions([
            'seo_analytics_scripts' => new AnalyticsTwigFunctions($context->settings()),
        ]);
    }

    /**
     * migrate() takes exactly one CREATE/ALTER/DROP TABLE statement per
     * call (it extracts a single table name via regex to check ownership),
     * so each table is its own call rather than one multi-statement file.
     */
    public function install(ModuleContext $context): void
    {
        $context->database()->migrate(<<<SQL
            CREATE TABLE IF NOT EXISTS mod_arout_seo_toolkit_pro_audits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                route_path VARCHAR(255) NOT NULL,
                source_type ENUM('static', 'sample_url') NOT NULL DEFAULT 'static',
                route_pattern VARCHAR(255) NULL,
                title VARCHAR(255) NULL,
                title_length INT UNSIGNED NULL,
                meta_description TEXT NULL,
                meta_description_length INT UNSIGNED NULL,
                h1_count INT UNSIGNED NOT NULL DEFAULT 0,
                images_total INT UNSIGNED NOT NULL DEFAULT 0,
                images_missing_alt INT UNSIGNED NOT NULL DEFAULT 0,
                has_canonical TINYINT(1) NOT NULL DEFAULT 0,
                word_count INT UNSIGNED NOT NULL DEFAULT 0,
                readability_score DECIMAL(5,2) NULL,
                readability_label VARCHAR(64) NULL,
                keywords TEXT NULL,
                scanned_at DATETIME NOT NULL,
                UNIQUE KEY uniq_route_path (route_path(191))
            )
            SQL);

        $context->database()->migrate(<<<SQL
            CREATE TABLE IF NOT EXISTS mod_arout_seo_toolkit_pro_404s (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                url VARCHAR(512) NOT NULL,
                referrer VARCHAR(512) NULL,
                ip_address VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                occurred_at DATETIME NOT NULL,
                KEY idx_url (url(191)),
                KEY idx_occurred_at (occurred_at)
            )
            SQL);

        $context->database()->migrate(<<<SQL
            CREATE TABLE IF NOT EXISTS mod_arout_seo_toolkit_pro_redirects (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_path VARCHAR(512) NOT NULL,
                target_path VARCHAR(512) NOT NULL,
                status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
                hit_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_source_path (source_path(191))
            )
            SQL);

        $context->database()->migrate(<<<SQL
            CREATE TABLE IF NOT EXISTS mod_arout_seo_toolkit_pro_sample_urls (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                route_pattern VARCHAR(255) NOT NULL,
                sample_url VARCHAR(512) NOT NULL,
                label VARCHAR(255) NULL,
                created_at DATETIME NOT NULL
            )
            SQL);
    }

    public function uninstall(ModuleContext $context): void
    {
        $context->database()->migrate('DROP TABLE IF EXISTS mod_arout_seo_toolkit_pro_audits');
        $context->database()->migrate('DROP TABLE IF EXISTS mod_arout_seo_toolkit_pro_404s');
        $context->database()->migrate('DROP TABLE IF EXISTS mod_arout_seo_toolkit_pro_redirects');
        $context->database()->migrate('DROP TABLE IF EXISTS mod_arout_seo_toolkit_pro_sample_urls');
    }
}
