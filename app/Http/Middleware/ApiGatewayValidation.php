<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiGatewayValidation
{
    private const MAX_PAYLOAD_BYTES = 50 * 1024;

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        $safeMethod = in_array(
            $request->method(),
            ['GET', 'HEAD', 'OPTIONS'],
            true
        );

        /*
         * POST / PUT / PATCH requests must contain JSON.
         */
        if (! $safeMethod && ! $request->isJson()) {

            return response()->json([
                'message' => 'AuraPay API accepts JSON request bodies only.',
            ], 415);
        }

        /*
         * API client must request a JSON response.
         */
        if (! $request->wantsJson()) {

            return response()->json([
                'message' => 'AuraPay API returns JSON responses only.',
            ], 406);
        }

        /*
         * Reject unnecessarily large request bodies.
         */
        $payloadSize = strlen(
            (string) $request->getContent()
        );

        if ($payloadSize > self::MAX_PAYLOAD_BYTES) {

            return response()->json([
                'message' => 'Request payload too large.',
            ], 413);
        }

        return $next($request);
    }
}