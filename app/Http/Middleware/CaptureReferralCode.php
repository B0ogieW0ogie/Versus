<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    public const COOKIE = 'versus_ref';

    public const COOKIE_LIFETIME_MINUTES = 60 * 24 * 30;

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $code = $request->query('ref');
        if (is_string($code) && preg_match('/^[A-Z0-9]{4,16}$/', $code) === 1) {
            $response->headers->setCookie(cookie(
                self::COOKIE,
                $code,
                self::COOKIE_LIFETIME_MINUTES,
            ));
        }

        return $response;
    }
}
