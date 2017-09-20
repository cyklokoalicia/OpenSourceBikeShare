<?php

namespace BikeShare\Http\Controllers\Admin;

use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UsersController extends Controller
{

    public $usersRepo;


    public function __construct(UsersRepository $repository)
    {
        $this->usersRepo = $repository;
    }


    public function index()
    {
        $users = $this->usersRepo->with(['activeRents'])->all();

        return view('admin.users.index', [
            'users' => $users,
        ]);
    }


    public function create()
    {
        return view('admin.users.create');
    }


    public function store(Request $request)
    {
        $password = bcrypt(substr(md5(rand()), 0, 7));
        $request->request->add(['password' => $password]);

        $this->usersRepo->create($request->all());

        toastr()->success('User successfully created');

        return redirect()->route('admin.users.index');
    }


    public function edit($uuid)
    {
        if (!$user = $this->usersRepo->findByUuid($uuid)) {
            toastr()->warning('User not found!');
        }

        return view('admin.users.edit', [
            'user' => $user,
        ]);
    }


    public function update(Request $request, $uuid)
    {
        if (!$user = $this->usersRepo->findByUuid($uuid)) {
            toastr()->warning('User not found!');
        }

        $this->usersRepo->update($request->all(), $uuid, 'uuid');
        toastr()->success('User successfully updated');

        return redirect()->route('admin.users.index');
    }
}
