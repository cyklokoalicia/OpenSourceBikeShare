<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Mail\MailSenderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    /**
     * @Route("/login", name="login")
     */
    public function login(
        bool $isSmsSystemEnabled,
        AuthenticationUtils $authenticationUtils
    ): Response {
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

    /**
     * @Route("/logout", name="logout")
     */
    public function logout(): void
    {
        // controller can be blank: it will never be executed!
        throw new \Exception('Don\'t forget to activate logout in security.php');
    }

    /**
     * @Route("/resetPassword", name="reset_password")
     */
    public function resetPassword(
        bool $isSmsSystemEnabled,
        Request $request,
        MailSenderInterface $mailer,
        UserProviderInterface $userProvider,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator
    ): Response {
        if ($request->isMethod('POST')) {
            $number = $request->request->get('number');

            try {
                $user = $userProvider->loadUserByIdentifier($number);
            } catch (UserNotFoundException $e) {
                $user = null;
            }

            if (!is_null($user)) {
                mt_srand(crc32(microtime()));
                $plainPassword = substr(md5(mt_rand() . microtime() . $user->getUsername()), 0, 8);
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $plainPassword
                );
                $userProvider->upgradePassword($user, $hashedPassword);

                $subject = $translator->trans('Password reset');
                $names = preg_split("/[\s,]+/", $user->getUsername());
                $firstname = $names[0];
                $message = $translator->trans('Hello') . ' ' . $firstname . ",\n\n" .
                    $translator->trans('Your password has been reset successfully.') . "\n\n" .
                    $translator->trans('Your new password is:') . "\n" . $plainPassword;

                $mailer->sendMail($user->getEmail(), $subject, $message);
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
