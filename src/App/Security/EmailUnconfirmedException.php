<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

/**
 * Thrown by {@see UserConfirmedEmailChecker} when an account still has a pending
 * email confirmation. A dedicated type lets the API layer map *only* this case to
 * the stable `email_unconfirmed` code, without mislabeling other future
 * AccountStatusException causes (e.g. locked/disabled accounts).
 */
class EmailUnconfirmedException extends CustomUserMessageAccountStatusException
{
}
