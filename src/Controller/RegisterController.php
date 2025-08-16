<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Form\RegistrationFormType;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\User\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterController extends AbstractController
{
    #[Route('/register', name: 'register')]
    #[Route('/register.php', name: 'register_old')]
    public function index(
        Request $request,
        TranslatorInterface $translator,
        UserRegistration $userRegistration,
        PhonePurifierInterface $phonePurifier,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $data['number'] = $phonePurifier->purify($data['number']);

            $user = $userRegistration->register(
                $data['number'],
                $data['useremail'],
                $data['password'],
                $data['city'],
                $data['fullname'],
                0
            );

            /**
             * @phpcs:disable Generic.Files.LineLength
             */
            $this->addFlash(
                'success',
                $translator->trans(
                    'You have been successfully registered. Please, check your email and read the instructions to finish your registration.'
                )
            );

            return $this->redirectToRoute('home');
        }

        return $this->render('register.html.twig', [
            'form' => $form,
        ]);
    }
}
