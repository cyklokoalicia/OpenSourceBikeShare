<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Form\ChangePasswordFormType;
use BikeShare\Form\ResetPasswordRequestFormType;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Notification\UserRecipient;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\PasswordResetRepository;
use BikeShare\Repository\RefreshTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityController extends AbstractController
{
    private const PASSWORD_RESET_LIFETIME_MINUTES = 60;

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
        PhonePurifierInterface $phonePurifier,
        PasswordResetRepository $passwordResetRepository,
        RateLimiterFactory $passwordResetIpLimiter,
        RateLimiterFactory $passwordResetNumberLimiter,
        TranslatorInterface $translator
    ): Response {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$passwordResetIpLimiter->create($request->getClientIp())->consume()->isAccepted()) {
                $this->addFlash('error', $translator->trans('password.reset.too_many'));

                return $this->redirectToRoute('reset_password');
            }

            $number = (string)$form->get('number')->getData();
            try {
                $number = $phonePurifier->purify($number);
            } catch (\InvalidArgumentException) {
                // Not a phone number: limit the raw value, it cannot match a user anyway.
            }
            // The per-number limit is consumed for unknown numbers too, so the two cases cannot be told apart.
            if ($passwordResetNumberLimiter->create($number)->consume()->isAccepted()) {
                try {
                    $user = $userProvider->loadUserByIdentifier($number);
                } catch (UserNotFoundException) {
                    $user = null;
                }
                if ($user instanceof User) {
                    $this->mailResetLink($user, $passwordResetRepository, $notifier);
                }
            }

            // The same answer whether or not the number is registered, so it cannot be used to find accounts.
            $this->addFlash('success', $translator->trans('password.reset.requested'));

            return $this->redirectToRoute('reset_password');
        }

        return $this->render(
            'security/reset_password.html.twig',
            [
                'form' => $form,
                'isSmsSystemEnabled' => $isSmsSystemEnabled,
            ]
        );
    }

    public function resetPasswordConfirm(
        string $token,
        Request $request,
        UserProvider $userProvider,
        PasswordResetRepository $passwordResetRepository,
        RefreshTokenRepository $refreshTokenRepository,
        UserPasswordHasherInterface $passwordHasher,
        TranslatorInterface $translator
    ): Response {
        $userId = $passwordResetRepository->findUserIdByToken($token);
        if (is_null($userId)) {
            return $this->invalidResetLink($translator);
        }
        try {
            $user = $userProvider->loadUserById($userId);
        } catch (UserNotFoundException) {
            return $this->invalidResetLink($translator);
        }

        $form = $this->createForm(ChangePasswordFormType::class, null, ['require_current_password' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Claim the link before changing anything: of two concurrent submissions only one gets past here.
            if (!$passwordResetRepository->consume($token)) {
                return $this->invalidResetLink($translator);
            }
            $userProvider->upgradePassword(
                $user,
                $passwordHasher->hashPassword($user, $form->get('plainPassword')->getData())
            );
            // Whoever knew the old password may hold an app session: it ends with the reset.
            $refreshTokenRepository->revokeAllForUser($user->getUserId());

            $this->addFlash('success', $translator->trans('password.reset.done'));

            return $this->redirectToRoute('login');
        }

        $response = $this->render('security/reset_password_confirm.html.twig', ['form' => $form]);
        // The token is in the URL: keep it out of the Referer of anything the page loads.
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function mailResetLink(
        User $user,
        PasswordResetRepository $passwordResetRepository,
        NotifierInterface $notifier
    ): void {
        $token = bin2hex(random_bytes(32));
        $passwordResetRepository->save($user->getUserId(), $token, self::PASSWORD_RESET_LIFETIME_MINUTES);

        $names = preg_split("/[\s,]+/", $user->getUsername());
        $notifier->notify(
            new Notification(
                NotificationType::PasswordReset,
                new TranslatableMessage('Password reset'),
                new TranslatableMessage(
                    'password.reset.link.mail',
                    [
                        'name' => $names[0],
                        'resetUrl' => $this->generateUrl(
                            'reset_password_confirm',
                            ['token' => $token],
                            UrlGeneratorInterface::ABSOLUTE_URL
                        ),
                        'minutes' => self::PASSWORD_RESET_LIFETIME_MINUTES,
                    ]
                )
            ),
            new UserRecipient($user->getUserId())
        );
    }

    private function invalidResetLink(TranslatorInterface $translator): Response
    {
        $this->addFlash('error', $translator->trans('password.reset.invalid_link'));

        return $this->redirectToRoute('reset_password');
    }
}
