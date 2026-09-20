<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IdleSessionTimeout
{
    /*
     * Maximum inactivity period:
     *
     * 5 minutes × 60 seconds = 300 seconds.
     */
    private const TIMEOUT_SECONDS = 300;

    /**
     * Handle an incoming request.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
         * The idle timeout applies only
         * to authenticated users.
         */
        if (! Auth::check()) {
            return $next($request);
        }

        /*
         * Current Unix timestamp.
         */
        $now = now()->timestamp;

        /*
         * Read the timestamp of the previous
         * authenticated activity from the session.
         */
        $lastActivity =
            $request
                ->session()
                ->get(
                    'aurapay_last_activity'
                );

        /*
         * If we already have a previous activity
         * timestamp, calculate how long the user
         * has been inactive.
         */
        if ($lastActivity !== null) {
            $idleSeconds =
                $now - (int) $lastActivity;

            /*
             * Five minutes or more without
             * authenticated activity means
             * the session must be terminated.
             */
            if (
                $idleSeconds
                >= self::TIMEOUT_SECONDS
            ) {
                /*
                 * Remove authentication state.
                 */
                Auth::logout();

                /*
                 * Destroy the current session ID
                 * and its stored session data.
                 */
                $request
                    ->session()
                    ->invalidate();

                /*
                 * Generate a new CSRF token.
                 */
                $request
                    ->session()
                    ->regenerateToken();

                /*
                 * API requests receive a structured
                 * JSON response.
                 */
                if (
                    $request->expectsJson()
                    || $request->is('api/*')
                ) {
                    return response()->json([
                        'message' =>
                            'Session expired after 5 minutes of inactivity.',

                        'code' =>
                            'SESSION_IDLE_TIMEOUT',
                    ], 401);
                }

                /*
                 * Normal browser requests are
                 * redirected to the login page.
                 */
                return redirect(
                    '/login?reason=idle-timeout'
                );
            }
        }

        /*
         * This authenticated request counts
         * as user activity.
         *
         * Save the current timestamp for the
         * next protected request.
         */
        $request
            ->session()
            ->put(
                'aurapay_last_activity',
                $now
            );

        return $next($request);
    }
}