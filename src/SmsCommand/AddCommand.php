<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use BikeShare\App\Configuration;
use BikeShare\App\Entity\User;
use BikeShare\Purifier\PhonePurifier;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\User\UserRegistration;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddCommand extends AbstractCommand implements SmsCommandInterface
{
    protected const COMMAND_NAME = 'ADD';
    protected const MIN_PRIVILEGES_LEVEL = 1;

    private Configuration $configuration;
    private UserRegistration $userRegistration;
    private UserRepository $userRepository;
    private PhonePurifier $phonePurifier;

    public function __construct(
        TranslatorInterface $translator,
        Configuration $configuration,
        UserRegistration $userRegistration,
        UserRepository $userRepository,
        PhonePurifier $phonePurifier
    ) {
        parent::__construct($translator);
        $this->configuration = $configuration;
        $this->userRegistration = $userRegistration;
        $this->userRepository = $userRepository;
        $this->phonePurifier = $phonePurifier;
    }

    public function __invoke(User $user, string $email, string $phone, string $fullName): string
    {
        $phone = $this->phonePurifier->purify($phone);

        if (
            $phone < $this->configuration->get('countrycode') . "000000000"
            || $phone > ($this->configuration->get('countrycode') + 1) . "000000000"
        ) {
            throw new ValidationException(
                $this->translator->trans('User with this phone number already registered.')
            );
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(
                $this->translator->trans('Email address is incorrect.')
            );
        }

        $registeredUser = $this->userRepository->findItemByPhoneNumber($phone);
        if (!is_null($registeredUser)) {
            throw new ValidationException(
                $this->translator->trans('Invalid phone number.')
            );
        }
        $registeredUser = $this->userRepository->findItemByEmail($email);
        if (!is_null($registeredUser)) {
            throw new ValidationException(
                $this->translator->trans('User with this email already registered.')
            );
        }

        //TODO what about user password?
        $user = $this->userRegistration->register(
            $phone,
            $email,
            substr(md5(mt_rand() . microtime() . $fullName), 0, 8),
            $user->getCity(), //register user in the same city as the admin who added him
            $fullName,
            0 // privileges level
        );

        $message = $this->translator->trans(
            'User {userName} added. They need to read email and agree to rules before using the system.',
            ['userName' => $fullName]
        );

        return $message;
    }

    public function getHelpMessage(): string
    {
        return $this->translator->trans(
            'with email, phone, fullname: {example}',
            ['example' => 'ADD king@earth.com 0901456789 Martin Luther King Jr.']
        );
    }
}
