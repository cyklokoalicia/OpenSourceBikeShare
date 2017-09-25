<?php

namespace BikeShare\Http\Controllers\User\Me;

use BikeShare\Domain\Core\Request;
use BikeShare\Http\Controllers\Controller;
use Hash;

class MeController extends Controller
{

    public function profile()
    {
        return view()->with([
            'user' => auth()->user(),
        ]);
    }


    public function setAvatar(Request $request)
    {
        // TODO validation
        auth()->user()->addMediaFromRequest($request->avatar)->toMediaCollection('avatars');
        toastr()->success('avatar was updated');

        return response()->redirectToRoute('user.me');
    }


    public function changePassword(Request $request)
    {
        if (! Hash::check($request->old_password, auth()->user()->getAuthPassword())) {
            $this->response->errorBadRequest('Old password not match!');
        }

        auth()->user()->setAttribute('password', bcrypt($request->new_password));
        auth()->user()->save();
        toastr()->success('password was changed');

        return response()->redirectToRoute('user.me');
    }
}
