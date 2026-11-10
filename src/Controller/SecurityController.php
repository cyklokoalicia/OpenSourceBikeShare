<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    public function login(
        bool $isSmsSystemEnabled,
        AuthenticationUtils $authenticationUtils
    ): Response {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render(
            'security/login.html.twig',
            [
                'isSmsSystemEnabled' => $isSmsSystemEnabled,
                'last_username' => $lastUsername,
                'error' => $error,
            ]
        );
    }

    public function logout(): void
    {
        // controller can be blank: it will never be executed!
        throw new \Exception("Don't forget to activate logout in security.php");
    }

    public function resetPassword(
        bool $isSmsSystemEnabled,
        Request $request,
        NotifierInterface $notifier,
        UserProvider $userProvider,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator
    ): Response {
        if ($request->isMethod('POST')) {
            $number = $request->request->get('number');

            try {
                $user = $userProvider->loadUserByIdentifier($number);
            } catch (UserNotFoundException) {
                $user = null;
            }

            if (!is_null($user)) {
                mt_srand(crc32(microtime()));
                $plainPassword = substr(md5(mt_rand() . microtime() . $user->getUserIdentifier()), 0, 8);
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plainPassword
                );
                $userProvider->upgradePassword($user, $hashedPassword);

                $names = preg_split("/[\s,]+/", $user->getUsername());
                $notifier->notify(
                    new Notification(
                        NotificationType::PasswordReset,
                        new TranslatableMessage('Password reset'),
                        new TranslatableMessage(
                            'password.reset.mail',
                            ['name' => $names[0], 'password' => $plainPassword]
                        )
                    ),
                    new UserRecipient($user->getUserId())
                );
            }

            $this->addFlash(
                'success',
                $translator->trans('Your password has been reset successfully.')
                . ' '
                . $translator->trans('Check your email.')
            );

            return $this->redirectToRoute('reset_password');
        }

        return $this->render(
            'security/reset_password.html.twig',
            [
                'isSmsSystemEnabled' => $isSmsSystemEnabled,
            ]
        );
    }
}
