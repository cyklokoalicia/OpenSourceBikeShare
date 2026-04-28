<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\User\UserRegistration;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;

class AddCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'ADD';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    public function __construct(
        private readonly UserRegistration $userRegistration,
        private readonly UserRepository $userRepository,
        private readonly PhonePurifierInterface $phonePurifier
    ) {
    }

    public function __invoke(User $user, string $email, string $phone, string $fullName): TranslatableInterface
    {
        if (!$this->phonePurifier->isValid($phone)) {
            throw new ValidationException('user.error.invalid_phone');
        }

        $phone = $this->phonePurifier->purify($phone);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('user.error.invalid_email');
        }

        $fullName = strip_tags($fullName);

        if (!is_null($this->userRepository->findItemByPhoneNumber($phone))) {
            throw new ValidationException('user.error.phone_already_registered');
        }

        if (!is_null($this->userRepository->findItemByEmail($email))) {
            throw new ValidationException('user.error.email_already_registered');
        }

        $this->userRegistration->register(
            $phone,
            $email,
            substr(md5(mt_rand() . microtime() . $fullName), 0, 8),
            $user->getCity(),
            $fullName,
            0
        );

        return new TranslatableMessage(
            'command.add.success',
            ['userName' => $fullName]
        );
    }

    public function getHelpMessage(): TranslatableInterface
    {
        return new TranslatableMessage('command.add.help');
    }
}
