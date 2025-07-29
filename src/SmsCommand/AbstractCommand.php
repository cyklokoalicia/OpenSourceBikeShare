<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = '';
    protected const MIN_PRIVILEGES_LEVEL = 0;

    public function __construct(protected TranslatorInterface $translator)
    {
    }

    public static function getName(): string
    {
        return static::COMMAND_NAME;
    }

    abstract public function getHelpMessage(): string;

    public function checkPrivileges(User $user): void
    {
        if ($user->getPrivileges() < $this->getRequiredPrivileges()) {
            throw new ValidationException(
                $this->translator->trans(
                    'Sorry, this command is only available for the privileged users.'
                )
            );
        }
    }

    protected function getRequiredPrivileges()
    {
        return static::MIN_PRIVILEGES_LEVEL;
    }
}
