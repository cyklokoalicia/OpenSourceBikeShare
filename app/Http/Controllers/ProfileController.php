<?php
namespace BikeShare\Http\Controllers;

use BikeShare\Domain\User\UsersRepository;

class ProfileController extends Controller
{

    public $userRepo;

    public function __construct(UsersRepository $repository)
    {
        parent::__construct();
        $this->userRepo = $repository;
    }

    public function show($uuid)
    {
        if (! $user = $this->userRepo->with(['rents', 'activeRents'])->findByUuid($uuid)) {
            // TODO User not found
        }

        return view('profile')->with(['user' => $user]);
    }
}
