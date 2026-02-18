<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectWwwToNonWww
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if (str_starts_with($host, 'www.')) {
            $nonWwwHost = substr($host, 4);
            $url = $request->getSchemeAndHttpHost();
            $url = str_replace($host, $nonWwwHost, $url);
            $url .= $request->getRequestUri();

            return redirect()->away($url, 301);
        }

        return $next($request);
    }
}
