<?php

namespace BikeShare\Http\Middleware;

use Auth;
use Closure;

class AuthenticateAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! Auth::check() || ! Auth::user() || ! Auth::user()->hasRole('admin')) {
            return redirect()->route('admin.auth.login');
        }

        return $next($request);
    }
}
