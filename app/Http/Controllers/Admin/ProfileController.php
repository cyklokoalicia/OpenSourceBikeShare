<?php
namespace BikeShare\Http\Controllers\Admin;

use BikeShare\Domain\User\UsersRepository;
use BikeShare\Http\Controllers\Controller;

class ProfileController extends Controller
{

    public $userRepo;

    public function __construct(UsersRepository $repository)
    {
        $this->userRepo = $repository;
    }

    public function show($uuid)
    {
        if (! $user = $this->userRepo->with(['rents', 'activeRents'])->findByUuid($uuid)) {
            // TODO User not found
        }

        return view('admin.profile')->with(['user' => $user]);
    }
}
