<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Form;

use BikeShare\Form\RegistrationFormType;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;

class RegistrationFormTypeTest extends BikeSharingKernelTestCase
{
    /**
     * @dataProvider invalidDataProvider
     */
    public function testSubmitInvalidData(
        array $formData,
        array $expectedErrors = []
    ): void {
        $form = static::getContainer()->get('form.factory')->create(RegistrationFormType::class);

        $form->submit($formData);

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $actualErrors = [];
        foreach ($form as $key) {
            if (empty($key->getErrors())) {
                continue;
            }
            foreach ($key->getErrors() as $error) {
                $actualErrors[$key->getName()][] = $error->getMessage();
            }
        }
        $this->assertEquals($expectedErrors, $actualErrors);
    }

    public function invalidDataProvider(): iterable
    {
        $expectedErrors = [
            'fullname' => [
                'Please, enter your firstname and lastname.'
            ],
            'useremail' => [
                'Email address is incorrect.'
            ],
            'password' => [
                'Password must be at least 6 characters long.'
            ],
            'number' => [
                'Invalid phone number.'
            ],
            'agree' => [
                'You must agree with the terms and conditions to register.'
            ],
        ];

        yield 'empty data' => [
            'formData' => [],
            'expectedErrors' => $expectedErrors,
        ];
        yield 'not full user name' => [
            'formData' => [
                'fullname' => 'fullname',
            ],
            'expectedErrors' => $expectedErrors,
        ];
        yield 'invalid email' => [
            'formData' => [
                'useremail' => 'useremail',
            ],
            'expectedErrors' => $expectedErrors,
        ];
        yield 'existing user email' => [
            'formData' => [
                'useremail' => 'test_registration_email@gmail.com',
            ],
            'expectedErrors' => array_replace(
                $expectedErrors,
                [
                    'useremail' => [
                        'User with this email already registered.',
                    ]
                ]
            ),
        ];
        $expectedErrorsPass = $expectedErrors;
        unset($expectedErrorsPass['password']);
        yield 'not matched password' => [
            'formData' => [
                'password' => 'password',
                'password2' => 'password2',
            ],
            'expectedErrors' => array_replace(
                $expectedErrorsPass,
                [
                    'password2' => [
                        'Passwords do not match.',
                    ]
                ]
            ),
        ];
        yield 'invalid city' => [
            'formData' => [
                'city' => 'InvalidCity',
            ],
            'expectedErrors' => $expectedErrors,
        ];
        yield 'existing user by number' => [
            'formData' => [
                'number' => '421333333333',
            ],
            'expectedErrors' => array_replace(
                $expectedErrors,
                [
                    'number' => [
                        'User with this phone number already registered.',
                    ]
                ]
            ),
        ];
    }

    /**
     * @dataProvider phonePurifyDataProvider
     */
    public function testPhonePurify(
        string $phone,
        string $expectedPhone
    ) {
        $form = static::getContainer()->get('form.factory')->create(RegistrationFormType::class);

        $form->submit(
            [
                'number' => $phone,
            ]
        );

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertSame($expectedPhone, $form->get('number')->getData());
    }

    public function phonePurifyDataProvider(): iterable
    {
        yield 'default' => [
            'phone' => '421333333333',
            'expectedPhone' => '421333333333',
        ];
        yield 'without international code' => [
            'phone' => '0333333333',
            'expectedPhone' => '421333333333',
        ];
    }

    /**
     * @dataProvider fullNamePurifyDataProvider
     */
    public function testFullNamePurify(
        string $fullname,
        string $expectedFullname
    ) {
        $form = static::getContainer()->get('form.factory')->create(RegistrationFormType::class);

        $form->submit(
            [
                'fullname' => $fullname,
            ]
        );

        // This check ensures there are no transformation failures
        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($form->isValid());

        $this->assertSame($expectedFullname, $form->get('fullname')->getData());
    }

    public function fullNamePurifyDataProvider(): iterable
    {
        yield 'default' => [
            'fullname' => 'Test User',
            'expectedFullname' => 'Test User',
        ];
        yield 'html code in name' => [
            'fullname' => '<a href="#">Test User</a>',
            'expectedFullname' => 'Test User',
        ];
        yield 'xss code in name' => [
            'fullname' => '<script>alert(Test);</script> User',
            'expectedfullname' => 'alert(Test); User',
        ];
    }
}
