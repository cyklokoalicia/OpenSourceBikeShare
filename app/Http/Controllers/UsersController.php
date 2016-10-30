<?php
namespace BikeShare\Http\Controllers;

use BikeShare\Domain\User\UsersRepository;

class UsersController extends Controller
{

    public $usersRepo;

    public function __construct(UsersRepository $repository)
    {
        $this->usersRepo = $repository;
    }

    public function index()
    {
        $users = $this->usersRepo->all();

        return view('users.index')->with(['users' => $users]);
    }
}
