<?php

declare(strict_types=1);

namespace BikeShare\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;

class ResetPasswordRequestFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('number', TextType::class, [
            'attr' => ['autofocus' => true],
            'constraints' => [new NotBlank()],
        ]);
    }
}
