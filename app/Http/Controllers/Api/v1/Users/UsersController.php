<?php

namespace BikeShare\Http\Controllers\Api\v1\Users;

use BikeShare\Domain\User\Events\UserWasRegistered;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use Illuminate\Http\Request;
use BikeShare\Http\Requests;

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
        if (! $user = $this->userRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('User not found');
        }

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
        if (! $user = $this->userRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('User not found');
        }

        $this->userRepo->updateRich($request->all(), $user->id);

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
        if (! $user = $this->userRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('User not found');
        }
        $user->delete();

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * @param $uuid
     *
     * @return \Dingo\Api\Http\Response|void
     */
    public function restore($uuid)
    {
        if (! $user = $this->userRepo->findByUuidWithTrashed($uuid)) {
            return $this->response->errorNotFound('User not found');
        }
        $user->restore();

        return $this->response->item($user, new UserTransformer());
    }
}
