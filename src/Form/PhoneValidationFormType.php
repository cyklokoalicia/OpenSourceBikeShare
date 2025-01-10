<?php

declare(strict_types=1);

namespace BikeShare\Form;

use BikeShare\Purifier\PhonePurifier;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhoneValidationFormType extends AbstractType
{
    private TranslatorInterface $translator;
    private PhonePurifier $phonePurifier;
    private UserRepository $userRepository;

    public function __construct(
        TranslatorInterface $translator,
        PhonePurifier $phonePurifier,
        UserRepository $userRepository
    ) {
        $this->translator = $translator;
        $this->phonePurifier = $phonePurifier;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('number', TelType::class, [
                'label' => $this->translator->trans('Phone number:'),
                'attr' => ['placeholder' => 'e.g., +1234567890']
            ]);

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            $form = $event->getForm();
            $data = $form->getData();

            // Validate number
            if (empty($data['number']) || strlen($data['number']) < 5) {
                $form->get('number')->addError(
                    new FormError(
                        $this->translator->trans('Phone number must be at least 5 characters long.')
                    )
                );
            }

            $phoneNumber = $this->phonePurifier->purify($data['number']);
            $user = $this->userRepository->findItemByPhoneNumber($phoneNumber);
            if (!is_null($user)) {
                $form->get('number')->addError(
                    new FormError(
                        $this->translator->trans('User with this phone number already registered.')
                    )
                );
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
