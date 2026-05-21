<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $allowedOrigins = array_values(array_filter(array_merge(
            [
                'http://localhost:3000',
                'http://127.0.0.1:3000',
            ],
            array_map('trim', explode(',', (string) env('FRONTEND_URL', '')))
        )));

        $origin = $request->headers->get('Origin');
        $isAllowedOrigin = in_array($origin, $allowedOrigins);

        if ($request->getMethod() === 'OPTIONS') {
            return $this->handlePreflightRequest($isAllowedOrigin ? $origin : '');
        }

        $response = $next($request);

        if ($isAllowedOrigin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function handlePreflightRequest($origin)
    {
        $response = response('', 204);

        if ($origin) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
