<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\History\HistoryAction;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class EmailConfirmController extends AbstractController
{
    #[Route('/user/confirm/email/{key}', name: 'user_confirm_email', defaults: ['key' => ''])]
    public function index(
        string $key,
        RegistrationRepository $registrationRepository,
        UserRepository $userRepository,
        HistoryRepository $historyRepository,
        TranslatorInterface $translator,
        int $userBikeLimitAfterRegistration = 0
    ): Response {
        if (!empty($key)) {
            $registration = $registrationRepository->findItem($key);
            if ($registration) {
                $registrationRepository->deleteItem($key);
                $user = $userRepository->findItem((int)$registration['userId']);
                if (empty($user['userLimit'])) {
                    $userRepository->updateUserLimit(
                        (int)$registration['userId'],
                        $userBikeLimitAfterRegistration
                    );
                }

                $historyRepository->addItem(
                    (int)$registration['userId'],
                    0,
                    HistoryAction::EMAIL_CONFIRMED,
                    ''
                );

                $this->addFlash(
                    'success',
                    $translator->trans(
                        'Your email is confirmed. Thank You. You can now log in to the system.'
                    )
                );

                return $this->redirectToRoute('home');
            }
        }

        $this->addFlash(
            'error',
            $translator->trans('Registration key not found!')
        );

        return $this->render('confirm.html.twig');
    }
}
