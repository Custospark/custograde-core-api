<?php

namespace App\Http\Middleware;

use App\Support\Capability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses a request whose role does not hold the required capability (SEC-07).
 *
 * Tenant scoping and authorisation are different questions and both must be
 * answered. `visibleTo` answers "is this your institution's row", which every
 * controller already checks. This answers "are you allowed to do this to it",
 * which until now nothing checked, so any user inside an institution could
 * release that institution's results.
 *
 * The refusal is a 403 with a stable machine-readable code, so a client can
 * tell "you may not do this" from "this does not exist" and say something
 * useful. It never reveals whether the underlying row exists.
 */
class RequireCapability
{
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $user = $request->user();

        if (! Capability::allowsUser($user, $capability)) {
            return response()->json([
                'message' => 'Your role does not allow this action.',
                'code' => 'insufficient_capability',
                'required' => $capability,
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}