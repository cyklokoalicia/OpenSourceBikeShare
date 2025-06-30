<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit;

use BikeShare\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RegistrationFormTypeTest extends KernelTestCase
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
        foreach ($form->getErrors() as $error) {
            $actualErrors[$error->getOrigin()->getName()] = $error->getMessage();
        }
        $this->assertSame($expectedErrors, $actualErrors);
    }

    public function invalidDataProvider(): iterable
    {
        yield 'empty data' => [
            [],
            [
                'city' => 'Please select your city.'
            ]
        ];
        yield 'invalid city' => [
            [
                'city' => 'InvalidCity'
            ],
            [
                'city' => 'Please select your city.'
            ]
        ];
    }
}
