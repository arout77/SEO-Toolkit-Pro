<?php

namespace Arout\SeoToolkitPro\Listeners;

use Rhapsody\Core\Events\RouteNotFound;
use Rhapsody\Core\Http\Response;

/**
 * NOTE: assumes RouteNotFound exposes getRequest(): Request (to read the
 * attempted path/referrer/IP) alongside the confirmed setResponse(Response).
 * Also assumes DatabaseFacade's fluent CRUD shape: ->table(name)->insert([]),
 * ->where(col, op, val)->first()/->update([]). Both unconfirmed — the
 * intent (check redirects, else log) won't change if the real calls differ.
 */
class RouteNotFoundListener
{
    public function __construct(private readonly object $database)
    {
    }

    public function __invoke(RouteNotFound $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPath();

        $redirect = $this->database
            ->table('mod_seo_toolkit_pro_redirects')
            ->where('source_path', '=', $path)
            ->first();

        if ($redirect) {
            $this->database
                ->table('mod_seo_toolkit_pro_redirects')
                ->where('id', '=', $redirect['id'])
                ->update(['hit_count' => $redirect['hit_count'] + 1]);

            $event->setResponse(Response::redirect($redirect['target_path'], (int) $redirect['status_code']));
            return;
        }

        // No redirect on file — log the raw hit and let it fall through to a real 404.
        $this->database->table('mod_seo_toolkit_pro_404s')->insert([
            'url' => $path,
            'referrer' => $request->header('referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('user-agent'),
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
