<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\ApiServiceUser;
use BikeShare\App\Entity\User;
use BikeShare\Db\DbInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly PhonePurifierInterface $phonePurifier,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     *
     * Symfony calls this method if you use features like switch_user
     * or remember_me. If you're not using these features, you do not
     * need to implement this method.
     *
     * @throws UserNotFoundException if the user is not found
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $identifier = $this->phonePurifier->purify($identifier);
        $result = $this->db->query(
            'SELECT userId, number, mail, password, city, userName, privileges, isNumberConfirmed, registrationDate
             FROM users 
             WHERE number = :identifier',
            [
                'identifier' => $identifier
            ]
        );
        if (!$result || $result->rowCount() == 0) {
            throw new UserNotFoundException(sprintf('Unknown user %s', $identifier));
        }

        $row = $result->fetchAssoc();

        return new User(
            (int)$row['userId'],
            $row['number'],
            $row['mail'],
            $row['password'],
            $row['city'],
            $row['userName'],
            (int)$row['privileges'],
            (bool)$row['isNumberConfirmed'],
            new \DateTimeImmutable($row['registrationDate']),
        );
    }

    /**
     * Refreshes the user after being reloaded from the session.
     *
     * When a user is logged in, at the beginning of each request, the
     * User object is loaded from the session and then this method is
     * called. Your job is to make sure the user's data is still fresh by,
     * for example, re-querying for fresh User data.
     *
     * If your firewall is "stateless: true" (for a pure API), this
     * method is not called.
     *
     * @return UserInterface
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if ($user instanceof ApiServiceUser) {
            return $user;
        }

        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        $user = $this->loadUserByIdentifier($user->getNumber());

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class) || $class === ApiServiceUser::class;
    }

    /**
     * Upgrades the hashed password of a user, typically for using a better hash algorithm.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $this->db->query(
            'UPDATE users SET password = :newHashedPassword WHERE number = :number',
            [
                'newHashedPassword' => $newHashedPassword,
                'number' => $user->getNumber()
            ]
        );

        $user = new User(
            $user->getUserId(),
            $user->getNumber(),
            $user->getEmail(),
            $newHashedPassword,
            $user->getCity(),
            $user->getUsername(),
            $user->getPrivileges(),
            $user->isNumberConfirmed(),
            $user->getRegistrationDate(),
        );
    }

    public function addUser(
        string $number,
        string $mail,
        string $plainPassword,
        string $city,
        string $userName,
        int $privileges,
        bool $isNumberConfirmed = false
    ): User {
        $registrationDate = $this->clock->now();
        $this->db->query(
            'INSERT INTO users (number, mail, password, city, userName, privileges, isNumberConfirmed, registrationDate)
               VALUES (:number, :mail, :plainPassword, :city, :userName, :privileges,
                       :isNumberConfirmed, :registrationDate)',
            [
                'number' => $number,
                'mail' => $mail,
                'plainPassword' => $plainPassword,
                'city' => $city,
                'userName' => $userName,
                'privileges' => $privileges,
                'isNumberConfirmed' => (int)$isNumberConfirmed,
                'registrationDate' => $registrationDate->format('Y-m-d H:i:s')
            ]
        );

        $userId = $this->db->getLastInsertId();

        return new User(
            $userId,
            $number,
            $mail,
            $plainPassword,
            $city,
            $userName,
            $privileges,
            $isNumberConfirmed,
            $registrationDate,
        );
    }
}
