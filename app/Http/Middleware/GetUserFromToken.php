<?php

namespace BikeShare\Http\Middleware;

use Closure;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class GetUserFromToken
{
    protected $auth;


    /**
     * Create a new BaseMiddleware instance
     *
     * @param JWTAuth|\Tymon\JWTAuth\JWTAuth $auth
     *
     * @internal param \Illuminate\Routing\ResponseFactory $response
     * @internal param \Illuminate\Events\Dispatcher $events
     */
    public function __construct(JWTAuth $auth)
    {
        $this->auth = $auth;
    }


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
        if (! $token = $this->auth->getToken()) {
            return $this->response('tymon.jwt.absent', 'Token not provided', 400);
        }

        try {
            $user = $this->auth->authenticate($token);
        } catch (TokenExpiredException $e) {
            return $this->respond('tymon.jwt.expired', 'Token expired', $e->getStatusCode(), [$e]);
        } catch (JWTException $e) {
            return $this->respond('tymon.jwt.invalid', 'Token invalid', $e->getStatusCode(), [$e]);
        }

        if (! $user) {
            return $this->respond('tymon.jwt.user_not_found', 'User not found', 404);
        }

        event('tymon.jwt.valid', $user);
        event('auth.login', $user);

        return $next($request);
    }
}
