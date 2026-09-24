<?php
namespace Arout\SeoToolkitPro\Twig;

/**
 * Invocable so it registers directly as a Twig function via the
 * twig.functions permission. TwigFacade::addFunction() prefixes the
 * registered name with mod_{slug}_, so the actual call a theme must add
 * near </head> is {{ mod_seo_toolkit_pro_seo_analytics_scripts()|raw }} —
 * not the bare name — same opt-in placement pattern as
 * {{ schema_markup|raw }}.
 */
class AnalyticsTwigFunctions
{
    public function __construct(private readonly object $settings)
    {
    }

    public function __invoke(): string
    {
        $tags = [];

        $ga4Id = $this->settings->get('analytics.ga4_id');
        if ($ga4Id) {
            $tags[] = <<<HTML
<script async src="https://www.googletagmanager.com/gtag/js?id={$ga4Id}"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '{$ga4Id}');
</script>
HTML;
        }

        $pixelId = $this->settings->get('analytics.meta_pixel_id');
        if ($pixelId) {
            $tags[] = <<<HTML
<script>
  !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
  n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
  n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
  t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
  document,'script','https://connect.facebook.net/en_US/fbevents.js');
  fbq('init', '{$pixelId}');
  fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
  src="https://www.facebook.com/tr?id={$pixelId}&ev=PageView&noscript=1" /></noscript>
HTML;
        }

        $custom = $this->settings->get('analytics.custom_script');
        if ($custom) {
            $tags[] = $custom;
        }

        return implode("\n", $tags);
    }
}
