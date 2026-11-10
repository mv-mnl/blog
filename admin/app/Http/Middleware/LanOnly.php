<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * El admin no tiene login: solo se permite desde la red local.
 * Aunque algún día se abra un puerto en el router, una IP de internet recibe 403.
 *
 * OJO: si publicas el admin con un túnel (Cloudflare Tunnel, ngrok…) o un proxy,
 * las peticiones llegan desde una IP interna y este filtro NO protege. No lo hagas
 * sin añadir antes un login.
 */
class LanOnly
{
    private const PRIVATE_RANGES = [
        '127.0.0.0/8', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '169.254.0.0/16', // IPv4
        '::1/128', 'fc00::/7', 'fe80::/10',                                                   // IPv6
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $ip = (string) $request->server('REMOTE_ADDR'); // la IP real de la conexión (no cabeceras)

        if (! IpUtils::checkIp($ip, self::PRIVATE_RANGES)) {
            abort(403, 'El panel de administración solo está disponible desde la red local.');
        }

        return $next($request);
    }
}
