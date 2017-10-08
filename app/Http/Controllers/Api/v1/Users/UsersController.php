<?php

namespace BikeShare\Http\Controllers\Api\v1\Users;

use BikeShare\Domain\User\Events\UserWasRegistered;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use Illuminate\Http\Request;

class UsersController extends Controller
{

    protected $userRepo;


    public function __construct(UsersRepository $repository)
    {
        $this->userRepo = $repository;
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $users = $this->userRepo->all();

        return $this->response->collection($users, new UserTransformer());
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $user = $this->userRepo->create($request->all());

        if ($request->filled('roles')) {
            $user->assignRole($request->roles);
        }
        event(new UserWasRegistered($user));

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * Display the specified resource.
     *
     * @param $uuid
     *
     * @return \Illuminate\Http\Response
     * @internal param int $id
     *
     */
    public function show($uuid)
    {
        $user = $this->userRepo->findByUuid($uuid);

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param                           $uuid
     *
     * @return \Illuminate\Http\Response
     * @internal param int $id
     *
     */
    public function update(Request $request, $uuid)
    {
        $user = $this->userRepo->findByUuid($uuid);
        $this->userRepo->update($request->all(), $user->id);

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param $uuid
     *
     * @return \Illuminate\Http\Response
     * @internal param int $id
     *
     */
    public function destroy($uuid)
    {
        $user = $this->userRepo->findByUuid($uuid);
        $user->delete();

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * @param $uuid
     *
     * @return \Dingo\Api\Http\Response
     */
    public function restore($uuid)
    {
        $user = $this->userRepo->findByUuidWithTrashed($uuid);
        $user->restore();

        return $this->response->item($user, new UserTransformer());
    }
}
