<?php

namespace App\Http\Middleware;

use App\Services\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureConsent
{
    public function __construct(
        private ConsentService $consents
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $scope
    ): Response {

        $user = $request->user();

        if (! $user) {

            return response()->json([
                'message' => 'Authentication required.',
            ], 401);
        }

        if (! $this->consents->hasActive(
            $user,
            $scope
        )) {

            return response()->json([
                'message' =>
                    "Missing active consent for scope '{$scope}'.",
                'required_scope' => $scope,
            ], 403);
        }

        return $next($request);
    }
}