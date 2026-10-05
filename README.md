# SEO Toolkit Pro (`arout/seo-toolkit-pro`)

Pro tier for `arout/seo-toolkit`. Depends on it via Composer; ships as its
own module rather than a license-flag inside Core.

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
3. Add `{{ mod_seo_toolkit_pro_seo_analytics_scripts()|raw }}` near
   `</head>` in every theme layout you want analytics tags to appear on —
   same opt-in pattern as `{{ schema_markup|raw }}`. Note the long name:
   `TwigFacade::addFunction()` auto-prefixes every registered function
   with `mod_{slug}_`, so the bare `seo_analytics_scripts()` name used in
   `ModuleProvider` isn't what's actually callable from a template.
4. **Current URL prefix is `/arout-seo-toolkit-pro/...`, not
   `/seo-toolkit-pro/...`.** Routes register under the module's
   vendor-joined slug for now — a fix to use the package-only slug instead
   is planned but not yet applied (tracked as its own phase in the
   module-rendering work, since it needs a collision-prevention mechanism
   first). Every path in this module will need updating when that lands.
   Visit `/arout-seo-toolkit-pro/settings` to configure GA4 / Meta Pixel /
   custom script, `/arout-seo-toolkit-pro/sample-urls` to register
   concrete URLs for parameterized routes, then `/arout-seo-toolkit-pro/audit`
   and hit "Run Scan".

## What's still assumed, not confirmed
Everything below is flagged inline with `NOTE:` comments at the point it's
used. A lot of what was originally guessed here has since been confirmed
against real pasted source and corrected — what's left:

- **`ModuleContext`'s own facade methods beyond `database()` and
  `twig()`** (both now fully confirmed) — `events()`, `routes()`,
  `settings()` haven't individually been checked against source, only
  inferred from working behavior.
- **`RouteNotFound` event** — confirmed to implement
  `StoppableEventInterface` with `setResponse(Response)` to stop
  propagation. Still assumed: that it also exposes `getRequest()`.
- **The crawler's internal request dispatch** (`RouteCrawler::render()`) —
  still assumes `Request::createInternal()` and a container-resolved
  `Router::dispatch()` exist for firing a request without going over real
  HTTP. Untested so far since nothing has actually triggered a scan yet.
- **`routes()->registered()`'s shape** — assumed `['method' => ...,
  'path' => ...]` rows, mirroring Core SEO Toolkit's sitemap generator.
- **The global `redirect()` helper** — assumed to exist as a bare global
  function, `redirect(string $path): Response`. Used now in
  `ProDashboardController::redirectWithFlash()`.
- **Flash messages** — rather than guess an unconfirmed `Session::flash()`
  signature, `redirectWithFlash()` sets `$_SESSION['flash_success']` /
  `$_SESSION['flash_error']` directly — the exact keys already confirmed
  live in `BaseController.php`. Should behave identically to whatever
  `Session::flash()` does internally if that's a thin wrapper over the
  same two keys; if there's more to it (expiry timing, structured data),
  this needs revisiting.

## Confirmed and fixed this round
- `TwigFacade` (`Rhapsody\Core\Modules\Facades\TwigFacade`) has
  `addExtension(ExtensionInterface $extension)` and `addFunction(string
  $name, callable $callback, array $options = [])` — not `extensions()`/
  `functions()` as originally guessed. `ModuleProvider::boot()` now calls
  `addFunction()` correctly.
- `ProDashboardController`'s `view()`/`redirect()` were placeholder stubs
  returning a plain array — `Router::execute()` requires a real
  `Rhapsody\Core\Response`, which is why every dashboard route 500'd with
  a `TypeError` once the routing-prefix issue was fixed. `view()` now
  calls `TwigFacade::render($template, $data): Response`; redirects go
  through the assumed global `redirect()` helper plus the direct
  `$_SESSION` flash-key writes described above.
- **Admin templates now extend `layouts/main.twig`, not `_layout.twig` or
  `layouts/admin.twig`** (both tried and both wrong). The real path is
  confirmed from this project's own theme-submission requirements:
  `views/themes/<theme_name>/layouts/main.twig`, required to define
  `title`, `description`, `styles`, `head_extensions`, `navigation`,
  `content`, `scripts` blocks. Only `content` is overridden here, which is
  fine — Twig inheritance only requires overriding blocks you want to
  customize.
- **`ProDashboardController::view()` now also supplies a `meta` array**
  (`meta.title` / `meta.description`) in the data passed to
  `TwigFacade::render()`. `main.twig`'s default `title`/`description`
  blocks render `{{ meta.title }}`/`{{ meta.description }}`, and the
  confirmed app convention is that `$this->view(...)` normally receives
  page meta as a third argument — `TwigFacade::render()` doesn't do that
  wrapping yet (explicitly deferred to Phase 5 in the module-rendering
  work), so `view()` builds the same `meta` shape manually here instead,
  pre-empting a possible undefined-variable error in the layout rather
  than waiting to hit it.
- **`TwigFacade::render()` doesn't auto-append `.twig`** — it only does
  `'@' . $this->slug . '/' . $view`, nothing more, confirmed directly from
  source. Every `$this->view('admin/xxx', ...)` call in
  `ProDashboardController` now passes the full `admin/xxx.twig` filename.
- **Views directory moved from `resources/views/` to `views/`.** A debug
  dump of `TwigFacade`'s real constructor showed the module loader
  computes each module's Twig path as `<install path>/views`, not
  `<install path>/resources/views` — the opposite of what had previously
  been logged as confirmed (that earlier conclusion didn't actually hold
  up against a live boot). `TwigFacade.php` itself is correct and was
  never the problem; this was purely a wrong assumption about a sibling
  class's path-building logic. All five admin templates now live under
  `views/admin/` at the package root.
