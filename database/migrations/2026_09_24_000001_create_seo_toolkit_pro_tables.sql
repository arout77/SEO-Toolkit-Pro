-- NOTE: Assumed migration convention — a plain, timestamp-prefixed .sql file
-- picked up by `rhapsody module:install arout/seo-toolkit-pro`. This has not
-- been confirmed against the real module installer / DatabaseFacade migration
-- runner. If modules actually ship migrations as PHP classes (mirroring
-- SkeletonMigrationInterface, or Phinx-style), tell me the expected shape and
-- I'll convert these straight over — the table definitions themselves won't
-- change either way.

CREATE TABLE IF NOT EXISTS mod_seo_toolkit_pro_audits (
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
    keywords TEXT NULL COMMENT 'JSON array of extracted keywords, used by the internal linking suggester',
    scanned_at DATETIME NOT NULL,
    UNIQUE KEY uniq_route_path (route_path)
);

CREATE TABLE IF NOT EXISTS mod_seo_toolkit_pro_404s (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(512) NOT NULL,
    referrer VARCHAR(512) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    occurred_at DATETIME NOT NULL,
    KEY idx_url (url(191)),
    KEY idx_occurred_at (occurred_at)
);

CREATE TABLE IF NOT EXISTS mod_seo_toolkit_pro_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(512) NOT NULL,
    target_path VARCHAR(512) NOT NULL,
    status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uniq_source_path (source_path(191))
);

CREATE TABLE IF NOT EXISTS mod_seo_toolkit_pro_sample_urls (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_pattern VARCHAR(255) NOT NULL COMMENT 'e.g. /products/{slug}, for display/grouping only',
    sample_url VARCHAR(512) NOT NULL COMMENT 'A real, concrete path the crawler can request, e.g. /products/leather-wallet',
    label VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
);
