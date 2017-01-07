<?php
namespace BikeShare\Http\Controllers;

use BikeShare\Domain\User\UsersRepository;

class UsersController extends Controller
{

    public $usersRepo;


    public function __construct(UsersRepository $repository)
    {
        parent::__construct();
        $this->usersRepo = $repository;
    }


    public function index()
    {
        $users = $this->usersRepo->with(['activeRents'])->all();

        return view('users.index', [
            'users' => $users,
        ]);
    }


    public function edit($id)
    {
        $user = $users = $this->usersRepo->find($id);

        return view('users.edit')->with(compact('user'));
    }
}
