<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\User;
use BikeShare\Db\DbInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private DbInterface $db;
    private PhonePurifierInterface $phonePurifier;

    public function __construct(
        DbInterface $db,
        PhonePurifierInterface $phonePurifier
    ) {
        $this->db = $db;
        $this->phonePurifier = $phonePurifier;
    }

    /**
     * @deprecated use loadUserByIdentifier() instead
     */
    public function loadUserByUsername(string $username)
    {
        return $this->loadUserByIdentifier($username);
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
            'SELECT userId, number, mail, password, city, userName, privileges 
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
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $user = $this->loadUserByIdentifier($user->getNumber());

        return $user;
    }

    /**
     * Tells Symfony to use this provider for this User class.
     */
    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
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
            $user->getPrivileges()
        );
    }

    public function addUser(
        string $number,
        string $mail,
        string $plainPassword,
        string $city,
        string $userName,
        int $privileges
    ): User {
        $this->db->query(
            'INSERT INTO users (number, mail, password, city, userName, privileges) 
               VALUES (:number, :mail, :plainPassword, :city, :userName, :privileges)',
            [
                'number' => $number,
                'mail' => $mail,
                'plainPassword' => $plainPassword,
                'city' => $city,
                'userName' => $userName,
                'privileges' => $privileges,
            ]
        );

        return new User(
            $this->db->getLastInsertId(),
            $number,
            $mail,
            $plainPassword,
            $city,
            $userName,
            $privileges
        );
    }
}
