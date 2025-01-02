<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\SmsCommand\Exception\ValidationException;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class AbstractCommand implements SmsCommandInterface
{
    protected const ARGUMENT_COUNT = 1;
    protected const COMMAND_NAME = '';
    protected const MIN_PRIVILEGES_LEVEL = 0;

    protected TranslatorInterface $translator;

    public function __construct(
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
    }

    public function execute(User $user, array $args): string
    {
        $this->checkPrivileges($user);
        $this->validate($args);

        return $this->run($user, $args);
    }

    public static function getName(): string
    {
        return static::COMMAND_NAME;
    }

    /**
     * @throws ValidationException
     */
    protected function validate(array $args): void
    {
        if (count($args) < $this->getRequiredArgsCount()) {
            throw new ValidationException(
                $this->translator->trans(
                    'Error. More arguments needed, use command {command}',
                    ['{command}' => $this->getValidationErrorMessage()]
                )
            );
        }
    }

    protected function checkPrivileges(User $user): void
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

    protected function getRequiredArgsCount(): int
    {
        return static::ARGUMENT_COUNT;
    }

    abstract protected function run(User $user, array $args): string;

    abstract protected function getValidationErrorMessage(): string;
}
