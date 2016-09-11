<?php
namespace BikeShare\Http\Controllers\Api\v1\Auth;

use BikeShare\Domain\Auth\Requests\LoginRequest;
use BikeShare\Http\Controllers\Api\v1\Controller;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class LoginController extends Controller
{

    public function authenticate(LoginRequest $request)
    {
        $credentials = $request->only('phone_number', 'password');

        try {
            if (! $token = JWTAuth::attempt($credentials)) {
                return $this->response->error('Invalid Credentials', 401);
            }
        } catch (JWTException $e) {
            return $this->response->error('Could not create token', 500);
        }

        //event(new UserLoggedIn(auth()->user()));

        return response()->json(compact('token'));
    }


    public function getAuthenticatedUser()
    {
        try {
            if (! $user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['user_not_found'], 404);
            }

        } catch (TokenExpiredException $e) {
            return response()->json(['token_expired'], $e->getStatusCode());

        } catch (TokenInvalidException $e) {
            return response()->json(['token_invalid'], $e->getStatusCode());

        } catch (JWTException $e) {
            return response()->json(['token_absent'], $e->getStatusCode());

        }

        // the token is valid and we have found the user via the sub claim
        return response()->json(compact('user'));
    }
}
