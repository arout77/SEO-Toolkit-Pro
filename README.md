# SEO Toolkit Pro (`arout/seo-toolkit-pro`)

Pro tier for `arout/seo-toolkit`. Depends on it via Composer; ships as its
own module (`seo-toolkit-pro` slug) rather than a license-flag inside Core.

## Features
- **On-page audit dashboard** — title/meta-description length, H1 count,
  image alt coverage, canonical presence, per crawled page.
- **Readability analysis** — Flesch Reading Ease score per page.
- **404 monitor** — every real 404 hit logged (URL, referrer, IP, UA,
  timestamp), aggregated on display, one-click promote to a redirect.
- **Redirect manager** — 301/302 table, matched before falling through to a
  real 404.
- **Internal linking suggestions** — keyword-overlap between page
  title/meta-description (deliberately the simple version — full
  content-similarity is a future upgrade, no content/tagging model exists
  in Rhapsody yet to build a stronger version on).
- **Analytics tag manager** — GA4, Meta Pixel, and a generic custom-script
  field, injected via a Twig function themes opt into explicitly.

## Setup
1. `composer require arout/seo-toolkit-pro`
2. Run `php rhapsody module:install arout/seo-toolkit-pro`, which runs
   `ModuleProvider::install()` — creates the module's four tables.
3. Add `{{ seo_analytics_scripts()|raw }}` near `</head>` in every theme
   layout you want analytics tags to appear on — same opt-in pattern as
   `{{ schema_markup|raw }}`.
4. Visit `/seo-toolkit-pro/settings` to configure GA4 / Meta Pixel / custom
   script, `/seo-toolkit-pro/sample-urls` to register concrete URLs for any
   parameterized routes (product pages, article pages, etc.) so the
   crawler has something to request, then `/seo-toolkit-pro/audit` and hit
   "Run Scan".

## What's assumed, not confirmed — check these against the real source
This was built without direct access to the Rhapsody Core source, working
from what's already documented/decided about it. Everything below is a
best-guess against those conventions and is flagged inline with `NOTE:`
comments at the point it's used:

- **`ModuleContext`'s namespace — now confirmed.** It's
  `Rhapsody\Core\Modules\ModuleContext`, not `Rhapsody\Core\Contracts\ModuleContext`
  as first guessed (that guess matched where `SkeletonMigrationInterface`
  lives, but the module system has its own `Modules\` namespace root —
  consistent with `ModuleServiceProviderInterface` and `DatabaseFacade`
  both living under `Rhapsody\Core\Modules\...` too). Its actual facade
  methods beyond `database()` (now fully confirmed, below) — `events()`,
  `routes()`, `settings()`, `twig()` — still haven't been checked against
  source.
- **`RouteNotFound` event shape** — confirmed: implements
  `StoppableEventInterface`, listener calls `setResponse(Response)` to stop
  propagation. Assumed but unconfirmed: it also exposes `getRequest()`.
- **Internal request dispatch for the crawler** (`RouteCrawler::render()`)
  — assumed `Request::createInternal()` and a container-resolved
  `Router::dispatch()` exist for firing a request without going over real
  HTTP. This is the single riskiest assumption in the package — if Core has
  no way to dispatch a synthetic in-process request, the crawler needs a
  different approach (e.g. shelling out to `file_get_contents()` against
  the live site instead of dispatching internally).
- **Routes table shape** — assumed `$routes->registered()` returns
  `['method' => ..., 'path' => ...]` rows, mirroring what the dynamic
  sitemap generator in Core SEO Toolkit already reads.
- **Module controller base class** — no shared base class name confirmed
  for module-owned controllers (only that app-level controllers extend
  `BaseController`). `ProDashboardController` currently has inline
  placeholder `view()`/`redirect()` methods standing in for whatever the
  real base gives modules — swap those out once confirmed.
- **`DatabaseFacade`'s real API — now confirmed from source, no longer a
  guess.** It's flat, not fluent: `select($table, $where, $columns)`,
  `insert($table, $data)`, `update($table, $data, $where)`,
  `delete($table, $where)` (the last two require a non-empty `$where`),
  `query($sql)` for read-only SQL on any table (used in `notFoundLog()`
  for the one place ordering was needed, since `select()` has no
  order-by), and `migrate($sql)` for schema — one `CREATE`/`ALTER`/`DROP
  TABLE` statement per call, table name extracted by regex to enforce the
  module can only touch its own `mod_arout_seo_toolkit_pro_*` tables. There's no
  `upsert()`, so `runScan()` does a manual select-then-insert-or-update.
  Schema now lives directly in `ModuleProvider::install()`/`uninstall()`
  as four `migrate()` calls rather than a separate `.sql` file, since
  `install()`/`uninstall()` turned out to be the real lifecycle hooks for
  this (see below) and `migrate()` only takes one statement at a time
  anyway.
- **`register()` vs `boot()` split** — confirmed the interface has both
  (plus `install()`/`uninstall()`, confirmed by a live fatal error naming
  all four as required). Current guess, now indirectly supported by
  `install()` running successfully up to the `raw()`/`migrate()` line:
  `register()` for bindings with no cross-module dependencies, `boot()`
  for anything that needs every module already registered — routes, the
  event listener, and the Twig function registration all live in
  `boot()`.

None of these change the actual logic (crawl → analyze → store; check
redirects → else log 404; expose analytics scripts via Twig) — they're all
isolated to the handful of lines that talk to Core, so point out the real
signatures and they're quick fixes.
