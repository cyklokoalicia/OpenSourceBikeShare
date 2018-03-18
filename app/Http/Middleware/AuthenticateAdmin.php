<?php

namespace BikeShare\Http\Middleware;

use Auth;
use Closure;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Session;

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

            if (Auth::user() && !Auth::user()->hasRole('admin')){

                $bag = new MessageBag();
                $bag->add('token', "User doesn't have admin privileges.");

                return redirect()->route('admin.auth.login')
                    ->with('errors', session()->get('errors', new ViewErrorBag)->put('default', $bag));

            }

            return redirect()->route('admin.auth.login');
        }

        return $next($request);
    }
}
