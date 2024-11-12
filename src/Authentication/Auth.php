<?php

namespace BikeShare\Authentication;

use Symfony\Component\Security\Core\Security;

/**
 * @deprecated
 */
class Auth
{
    private Security $security;

    public function __construct(
        Security $security
    ) {
        $this->security = $security;
    }

    public function getUserId()
    {
        /**
         * @var \BikeShare\App\Entity\User $user
         */
        $user = $this->security->getUser();
        if (!is_null($user)) {
            return $user->getUserId();
        } else {
            return 0;
        }
    }

    public function login($number, $password)
    {
        throw new \Exception("Deprecated method");
    }

    public function logout()
    {
        throw new \Exception("Deprecated method");
    }

    public function refreshSession()
    {
//        throw new \Exception("Deprecated method");
    }

    public function isLoggedIn()
    {
        return !is_null($this->security->getUser());
    }
}
