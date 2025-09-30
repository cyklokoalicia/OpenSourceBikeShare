<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Form\ChangePasswordFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    public function profile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserProvider $userProvider,
        TranslatorInterface $translator
    ): Response {
        $user = $this->getUser();
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $form->get('currentPassword')->addError(new FormError($translator->trans('Invalid current password.')));
            } else {
                $newPassword = $form->get('plainPassword')->getData();
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $newPassword
                );
                $userProvider->upgradePassword($user, $hashedPassword);
                $this->addFlash('success', $translator->trans('Password updated successfully.'));

                return $this->redirectToRoute('user_profile');
            }
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
