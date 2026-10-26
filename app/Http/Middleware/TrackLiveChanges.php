<?php

namespace App\Http\Middleware;

use App\Support\LiveSync;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLiveChanges
{
    public function __construct(private LiveSync $sync) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe()
            && $response->getStatusCode() < 400
            && $request->is('owner/*', 'waiter/*', 'barista/*', 'api/v1/*')
            && ! $request->is('api/v1/auth/*')) {
            $this->sync->changed();
        }

        return $response;
    }
}
