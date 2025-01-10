<?php

declare(strict_types=1);

namespace BikeShare\Form;

use BikeShare\App\Configuration;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationFormType extends AbstractType
{
    private bool $isSmsSystemEnabled;
    private TranslatorInterface $translator;
    private Configuration $configuration;
    private SessionInterface $session;
    private HistoryRepository $historyRepository;
    private UserRepository $userRepository;

    public function __construct(
        bool $isSmsSystemEnabled,
        TranslatorInterface $translator,
        Configuration $configuration,
        SessionInterface $session,
        HistoryRepository $historyRepository,
        UserRepository $userRepository
    ) {
        $this->isSmsSystemEnabled = $isSmsSystemEnabled;
        $this->translator = $translator;
        $this->configuration = $configuration;
        $this->session = $session;
        $this->historyRepository = $historyRepository;
        $this->userRepository = $userRepository;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $isSmsSystemEnabled = $this->isSmsSystemEnabled;

        $builder
            ->add('fullname', TextType::class, [
                'label' => $this->translator->trans('Fullname:'),
                'attr' => ['placeholder' => $this->translator->trans('Firstname Lastname')]
            ]);
        $cities = $this->configuration->get('cities');
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
        if ($isSmsSystemEnabled) {
            $builder->add('smscode', TextType::class, [
                'label' => $this->translator->trans('SMS code (received to your phone):')
            ]);
        }

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($isSmsSystemEnabled) {
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
                } elseif (filter_var($data['useremail'], FILTER_VALIDATE_EMAIL)) {
                    $registeredUser = $this->userRepository->findItemByEmail($data['useremail']);
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
                    $cities = $this->configuration->get('cities');
                    if (!in_array($data['city'], $cities)) {
                        $form->get('city')->addError(
                            new FormError(
                                $this->translator->trans('Please select your city.')
                            )
                        );
                    }
                }

                if ($isSmsSystemEnabled) {
                    if (empty($data['smscode'])) {
                        $form->get('smscode')->addError(
                            new FormError(
                                $this->translator->trans('Please, enter SMS code received to your phone.')
                            )
                        );
                    } else {
                        $number = $this->session->get('validatedNumber');
                        $checkCode = $this->session->get('checkCode');
                        $parameter = $number . ';' . str_replace(' ', '', $data['smscode']) . ';' . $checkCode;
                        $history = $this->historyRepository->findRegistration($parameter);
                        if (is_null($history)) {
                            $form->get('smscode')->addError(
                                new FormError(
                                    $this->translator->trans(
                                        'Problem with the SMS code entered. Please check and try again.'
                                    )
                                )
                            );
                        }
                    }
                }
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
