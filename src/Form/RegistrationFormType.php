<?php

declare(strict_types=1);

namespace BikeShare\Form;

use BikeShare\App\Configuration;
use BikeShare\Purifier\PhonePurifier;
use BikeShare\Repository\CityRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationFormType extends AbstractType
{
    private CityRepository $cityRepository;
    private TranslatorInterface $translator;
    private Configuration $configuration;
    private PhonePurifier $phonePurifier;
    private UserRepository $userRepository;

    public function __construct(
        CityRepository $cityRepository,
        TranslatorInterface $translator,
        Configuration $configuration,
        PhonePurifier $phonePurifier,
        UserRepository $userRepository
    ) {
        $this->cityRepository = $cityRepository;
        $this->translator = $translator;
        $this->configuration = $configuration;
        $this->phonePurifier = $phonePurifier;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fullname', TextType::class, [
                'label' => $this->translator->trans('Fullname:'),
                'attr' => ['placeholder' => $this->translator->trans('Firstname Lastname')]
            ]);
        $cities = array_keys($this->cityRepository->findAvailableCities());
        if (count($cities) > 1) {
            $choices = [
                '' => $this->translator->trans('Select your city'),
            ];
            foreach ($cities as $city) {
                $choices[$city] = $this->translator->trans($city);
            }
            $builder->add('city', ChoiceType::class, [
                'label' => $this->translator->trans('City:'),
                'choices' => $choices,
            ]);
        } else {
            $builder->add('city', HiddenType::class, [
                'data' => $cities[0]
            ]);
        }
        $builder->add('useremail', EmailType::class, [
                'label' => $this->translator->trans('Email:'),
                'attr' => ['placeholder' => 'email@domain.com']
            ])
            ->add('password', PasswordType::class, [
                'label' => $this->translator->trans('Password:')
            ])
            ->add('password2', PasswordType::class, [
                'label' => $this->translator->trans('Password confirmation:')
            ]);

        $builder->add('number', TelType::class, [
            'label' => $this->translator->trans('Phone number:'),
            'attr' => ['placeholder' => 'e.g., +1234567890']
        ]);

        $builder->add('agree', CheckboxType::class, [
            'label' => $this->translator->trans(
                'By registering I confirm that I have read: {systemRules} and agree with the terms and conditions.',
                [
                    'systemRules' => '<a href="' . $this->configuration->get('systemrules') . '" target="_blank">'
                        . $this->translator->trans('User Guide') . '</a>'
                ]
            ),
            'label_html' => true,
        ]);

        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event) {
                $data = $event->getData();
                $data['number'] = $this->phonePurifier->purify($data['number'] ?? '');
                $data['fullname'] = strip_tags($data['fullname'] ?? '');
                $event->setData($data);
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) {
                $form = $event->getForm();
                $data = $form->getData();
                $data['fullname'] = trim(preg_replace('/\s+/', ' ', $data['fullname'] ?? ''));

                if (empty($data['fullname']) || count(explode(' ', trim($data['fullname']))) < 2) {
                    $form->get('fullname')->addError(
                        new FormError(
                            $this->translator->trans('Please, enter your firstname and lastname.')
                        )
                    );
                }
                if (empty($data['useremail']) || !filter_var($data['useremail'], FILTER_VALIDATE_EMAIL)) {
                    $form->get('useremail')->addError(
                        new FormError(
                            $this->translator->trans('Email address is incorrect.')
                        )
                    );
                } else {
                    $registeredUser = $this->userRepository->findItemByEmail($data['useremail']);
                    //perhaps we should not allow to check mail address...
                    if (!is_null($registeredUser)) {
                        $form->get('useremail')->addError(
                            new FormError(
                                $this->translator->trans('User with this email already registered.')
                            )
                        );
                    }
                }

                if (empty($data['password']) || strlen($data['password']) < 6) {
                    $form->get('password')->addError(
                        new FormError(
                            $this->translator->trans('Password must be at least 6 characters long.')
                        )
                    );
                } elseif (empty($data['password2']) || $data['password'] !== $data['password2']) {
                    $form->get('password2')->addError(
                        new FormError(
                            $this->translator->trans('Passwords do not match.')
                        )
                    );
                }

                if (empty($data['city'])) {
                    $form->get('city')->addError(
                        new FormError(
                            $this->translator->trans('Please select your city.')
                        )
                    );
                } else {
                    $cities = array_keys($this->cityRepository->findAvailableCities());
                    if (!in_array($data['city'], $cities)) {
                        $form->get('city')->addError(
                            new FormError(
                                $this->translator->trans('Please select your city.')
                            )
                        );
                    }
                }

                if (empty($data['number']) || strlen($data['number']) < 5) {
                    $form->get('number')->addError(
                        new FormError(
                            $this->translator->trans('Invalid phone number.')
                        )
                    );
                } else {
                    //perhaps we should not allow to check number...
                    $user = $this->userRepository->findItemByPhoneNumber($data['number']);
                    if (!is_null($user)) {
                        $form->get('number')->addError(
                            new FormError(
                                $this->translator->trans('User with this phone number already registered.')
                            )
                        );
                    }
                }

                if (empty($data['agree'])) {
                    $form->get('agree')->addError(
                        new FormError(
                            $this->translator->trans(
                                'You must agree with the terms and conditions to register.'
                            )
                        )
                    );
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
