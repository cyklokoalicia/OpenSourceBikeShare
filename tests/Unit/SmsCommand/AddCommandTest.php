<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\SmsCommand;

use BikeShare\App\Entity\User;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsCommand\AddCommand;
use BikeShare\SmsCommand\Exception\ValidationException;
use BikeShare\User\UserRegistration;
use Generator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class AddCommandTest extends TestCase
{
    /** @var TranslatorInterface|MockObject */
    private $translatorMock;
    /** @var UserRegistration|MockObject */
    private $userRegistrationMock;
    /** @var UserRepository|MockObject */
    private $userRepositoryMock;
    /** @var PhonePurifierInterface|MockObject */
    private $phonePurifierMock;

    private AddCommand $command;

    protected function setUp(): void
    {
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->userRegistrationMock = $this->createMock(UserRegistration::class);
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->phonePurifierMock = $this->createMock(PhonePurifierInterface::class);

        $this->command = new AddCommand(
            $this->translatorMock,
            $this->userRegistrationMock,
            $this->userRepositoryMock,
            $this->phonePurifierMock
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->translatorMock,
            $this->userRegistrationMock,
            $this->userRepositoryMock,
            $this->phonePurifierMock,
            $this->command
        );
    }

    /** @dataProvider invokeThrowsValidationDataProvider */
    public function testInvokeThrowsValidation(
        string $phonePurifierCallResult,
        bool $isValid,
        array $userRepositoryByPhoneCallParams,
        ?array $userRepositoryByPhoneCallResult,
        array $userRepositoryByEmailCallParams,
        ?array $userRepositoryByEmailCallResult,
        string $email,
        string $message
    ): void {
        $userMock = $this->createMock(User::class);
        $phone = '123456789';

        $this->phonePurifierMock
            ->expects($this->once())
            ->method('isValid')
            ->with($phone)
            ->willReturn($isValid);
        $this->phonePurifierMock
            ->expects($isValid ? $this->once() : $this->never())
            ->method('purify')
            ->with($phone)
            ->willReturn($phonePurifierCallResult);
        $this->userRepositoryMock
            ->expects($this->exactly(count($userRepositoryByPhoneCallParams)))
            ->method('findItemByPhoneNumber')
            ->with($phonePurifierCallResult)
            ->willReturn($userRepositoryByPhoneCallResult);
        $this->userRepositoryMock
            ->expects($this->exactly(count($userRepositoryByEmailCallParams)))
            ->method('findItemByEmail')
            ->with($email)
            ->willReturn($userRepositoryByEmailCallResult);
        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with($message)
            ->willReturn($message);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($message);

        ($this->command)($userMock, $email, $phone, 'Test User');
    }

    public function testInvokeSuccessRegister(): void
    {
        $userMock = $this->createMock(User::class);
        $phone = '123456789';
        $email = 'test@example.com';
        $purifiedPhone = '421123456789';
        $city = 'Bratislava';
        $fullName = 'Test User';
        $newUser = $this->createMock(User::class);
        $message = 'User Test User added. They need to read email and agree to rules before using the system.';

        $userMock->expects($this->once())->method('getCity')->willReturn($city);
        $this->phonePurifierMock->expects($this->once())->method('isValid')->with($phone)->willReturn(true);
        $this->phonePurifierMock->expects($this->once())->method('purify')->with($phone)->willReturn($purifiedPhone);
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findItemByPhoneNumber')
            ->with($purifiedPhone)
            ->willReturn(null);
        $this->userRepositoryMock->expects($this->once())->method('findItemByEmail')->with($email)->willReturn(null);

        $this->userRegistrationMock
            ->expects($this->once())
            ->method('register')
            ->with($purifiedPhone, $email, $this->anything(), $city, $fullName, 0)
            ->willReturn($newUser);

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'User {userName} added. They need to read email and agree to rules before using the system.',
                ['userName' => $fullName]
            )
            ->willReturn($message);

        $this->assertEquals($message, ($this->command)($userMock, $email, $phone, $fullName));
    }

    public function testGetHelpMessage(): void
    {
        $message = 'with email, phone, fullname: ADD king@earth.com 0901456789 Martin Luther King Jr.';

        $this->translatorMock
            ->expects($this->once())
            ->method('trans')
            ->with(
                'with email, phone, fullname: {example}',
                ['example' => 'ADD king@earth.com 0901456789 Martin Luther King Jr.']
            )
            ->willReturn($message);

        $this->assertEquals($message, $this->command->getHelpMessage());
    }

    public function invokeThrowsValidationDataProvider(): Generator
    {
        yield 'phone number invalid' => [
            'phonePurifierCallResult' => '420123456789',
            'isValid' => false,
            'userRepositoryByPhoneCallParams' => [],
            'userRepositoryByPhoneCallResult' => null,
            'userRepositoryByEmailCallParams' => [],
            'userRepositoryByEmailCallResult' => null,
            'email' => 'test@example.com',
            'message' => 'Invalid phone number.',
        ];
        yield 'email invalid' => [
            'phonePurifierCallResult' => '421123456789',
            'isValid' => true,
            'userRepositoryByPhoneCallParams' => [],
            'userRepositoryByPhoneCallResult' => null,
            'userRepositoryByEmailCallParams' => [],
            'userRepositoryByEmailCallResult' => null,
            'email' => 'abc',
            'message' => 'Email address is incorrect.',
        ];
        yield 'user with phone already exists' => [
            'phonePurifierCallResult' => '421123456789',
            'isValid' => true,
            'userRepositoryByPhoneCallParams' => ['421123456789'],
            'userRepositoryByPhoneCallResult' => ['id' => 123],
            'userRepositoryByEmailCallParams' => [],
            'userRepositoryByEmailCallResult' => null,
            'email' => 'test@example.com',
            'message' => 'User with this phone number already registered.',
        ];
        yield 'user with email already exists' => [
            'phonePurifierCallResult' => '421123456789',
            'isValid' => true,
            'userRepositoryByPhoneCallParams' => ['421123456789'],
            'userRepositoryByPhoneCallResult' => null,
            'userRepositoryByEmailCallParams' => ['test@example.com'],
            'userRepositoryByEmailCallResult' => ['id' => 123],
            'email' => 'test@example.com',
            'message' => 'User with this email already registered.',
        ];
    }
}
