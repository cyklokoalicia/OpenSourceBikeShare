<?php

namespace BikeShare\Http\Middleware;

use Closure;

class RoleMiddleware
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     * @param                           $role
     * @param null                      $permission
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $role, $permission = null)
    {
        if (auth()->guest()) {
            return response()->json('Unauthorized', 401);
        }

        if (! $request->user()->hasRole($role)) {
            return response()->json('You are not permitted to perform this action.', 401);
        }

        if ($permission && ! $request->user()->can($permission)) {
            return response()->json('You do not have permission to perform this action.', 401);
        }

        return $next($request);
    }
}
