<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Welcome\MessengerChatsProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WelcomeController extends AbstractController
{
    public function index(MessengerChatsProvider $messengerChatsProvider): Response
    {
        if (!$messengerChatsProvider->hasChats()) {
            return $this->redirectToRoute('home');
        }

        return $this->render('welcome.html.twig', [
            'chats' => $messengerChatsProvider->getChats(),
        ]);
    }

    public function dismiss(UserSettingsRepository $userSettingsRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $userSettingsRepository->saveShowWelcomePage($user->getUserId(), false);

        return $this->redirectToRoute('home');
    }
}
