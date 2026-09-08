<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ResponseMetrics;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Mencatat laju galat dan waktu respons endpoint siswa untuk dasbor Monitor.
 */
class RecordResponseMetrics
{
    public function __construct(private readonly ResponseMetrics $metrics) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $response = $next($request);

        try {
            $this->metrics->record(
                route: $this->label($request),
                status: $response->getStatusCode(),
                durationMs: (microtime(true) - $startedAt) * 1000,
            );
        } catch (Throwable) {
            // Pemantauan tidak boleh menjatuhkan permintaan siswa.
        }

        return $response;
    }

    private function label(Request $request): string
    {
        $name = $request->route()?->uri() ?? 'unknown';

        return str_replace(['api/t/{token}/', 't/{token}/'], '', $name) ?: 'root';
    }
}
