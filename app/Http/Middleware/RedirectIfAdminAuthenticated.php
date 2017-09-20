<?php

namespace BikeShare\Http\Middleware;

use Auth;
use Closure;

class RedirectIfAdminAuthenticated
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        //If request comes from logged in admin, he will
        //be redirected to admin's home page.
        if (Auth::check() && Auth::user() && Auth::user()->hasRole('admin')) {
            return redirect('/admin/dashboard');
        }

        return $next($request);
    }
}
