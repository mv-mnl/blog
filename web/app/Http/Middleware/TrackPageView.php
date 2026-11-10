<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cuenta visitas sin cookies ni servicios externos.
 * El visitante es un hash de (día + IP + navegador + clave secreta): no se guarda la IP
 * y el mismo visitante tiene un código distinto cada día, así que no se puede seguir a nadie.
 */
class TrackPageView
{
    private const BOTS = '/bot|crawl|spider|slurp|curl|wget|python|httpclient|okhttp|go-http|headless|preview|monitor|uptime|facebookexternalhit|embedly|lighthouse|feed|rss/i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET')
            && $response->getStatusCode() === 200
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && ! preg_match(self::BOTS, (string) $request->userAgent())
            && $request->header('Purpose') !== 'prefetch') {
            try {
                $referrer = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
                DB::table('page_views')->insert([
                    'post_id' => $request->attributes->get('post_id'),
                    'path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 255),
                    'referrer_host' => $referrer && $referrer !== $request->getHost() ? mb_substr($referrer, 0, 120) : null,
                    'visitor' => substr(hash('sha256', now()->toDateString().'|'.config('app.key').'|'.$request->ip().'|'.$request->userAgent()), 0, 16),
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e); // una visita sin contar nunca debe romper la página
            }
        }

        return $response;
    }
}
