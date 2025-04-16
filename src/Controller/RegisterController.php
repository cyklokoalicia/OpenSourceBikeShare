<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Form\RegistrationFormType;
use BikeShare\User\UserRegistration;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegisterController extends AbstractController
{
    /**
     * @Route("/register", name="register")
     * @Route("/register.php", name="register")
     */
    public function index(
        Request $request,
        SessionInterface $session,
        TranslatorInterface $translator,
        UserRegistration $userRegistration
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            $user = $userRegistration->register(
                $data['number'],
                $data['useremail'],
                substr(md5(mt_rand() . microtime() . $data['fullname']), 0, 8),
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
            'form' => $form->createView(),
        ]);
    }
}
