<?php

namespace Arout\SeoToolkitPro\Listeners;

use Rhapsody\Core\Events\RouteNotFound;
use Rhapsody\Core\Http\Response;
use Rhapsody\Core\Modules\Facades\DatabaseFacade;

/**
 * NOTE: still assumes RouteNotFound exposes getRequest(): Request — that
 * part of the event hasn't been confirmed the way DatabaseFacade now has.
 */
class RouteNotFoundListener
{
    public function __construct(private readonly DatabaseFacade $database)
    {
    }

    public function __invoke(RouteNotFound $event): void
    {
        $request = $event->getRequest();
        $path = $request->getPath();

        $matches = $this->database->select('mod_arout_seo_toolkit_pro_redirects', ['source_path' => $path]);
        $redirect = $matches[0] ?? null;

        if ($redirect) {
            $this->database->update(
                'mod_arout_seo_toolkit_pro_redirects',
                ['hit_count' => $redirect['hit_count'] + 1],
                ['id' => $redirect['id']]
            );

            $event->setResponse(Response::redirect($redirect['target_path'], (int) $redirect['status_code']));
            return;
        }

        // No redirect on file — log the raw hit and let it fall through to a real 404.
        $this->database->insert('mod_arout_seo_toolkit_pro_404s', [
            'url' => $path,
            'referrer' => $request->header('referer'),
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('user-agent'),
            'occurred_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
