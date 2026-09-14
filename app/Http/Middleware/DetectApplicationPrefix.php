<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApplicationPrefix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectApplicationPrefix
{
    public function handle(Request $request, Closure $next): Response
    {
        [, $request] = ApplicationPrefix::bind($request);

        return $next($request);
    }
}
