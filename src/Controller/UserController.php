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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserController extends AbstractController
{
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

    public function profile(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        UserProvider $userProvider,
        TranslatorInterface $translator,
        TokenStorageInterface $tokenStorage,
        RememberMeHandlerInterface $rememberMeHandler,
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

                // Keep this session signed in: with the old hash in the token the next request would see a
                // changed user and log out. Other sessions and remember-me cookies are invalidated on purpose.
                $refreshedUser = $userProvider->refreshUser($user);
                $tokenStorage->getToken()?->setUser($refreshedUser);
                if ($request->cookies->has(self::REMEMBER_ME_COOKIE)) {
                    $rememberMeHandler->createRememberMeCookie($refreshedUser);
                }

                $this->addFlash('success', $translator->trans('Password updated successfully.'));

                return $this->redirectToRoute('user_profile');
            }
        }

        return $this->render('user/profile.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
