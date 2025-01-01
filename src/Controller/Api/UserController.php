<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\App\Configuration;
use BikeShare\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    /**
     * @Route("/api/user", name="api_user_index", methods={"GET"})
     */
    public function index(
        UserRepository $userRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        $bikes = $userRepository->findAll();

        return $this->json($bikes);
    }

    /**
     * @Route("/api/user/{userId}", name="api_user_item", methods={"GET"}, requirements: {"userId"="\d+"})
     */
    public function item(
        $userId,
        UserRepository $userRepository,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        if (empty($userId) || !is_numeric($userId)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $user = $userRepository->findItem((int)$userId);

        return $this->json($user);
    }

    /**
     * @Route("/api/user/{userId}", name="api_user_item_update", methods={"PUT"}, requirements: {"userId"="\d+"})
     */
    public function update(
        $userId,
        Request $request,
        UserRepository $userRepository,
        Configuration $configuration,
        LoggerInterface $logger
    ): Response {
        if (!$this->isGranted('ROLE_ADMIN')) {
            $logger->info(
                'User tried to access admin page without permission',
                [
                    'user' => $this->getUser()->getUserIdentifier(),
                ]
            );

            return $this->json([], Response::HTTP_FORBIDDEN);
        }

        if (empty($userId) || !is_numeric($userId)) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $userName = $request->request->get('username');
        $email = $request->request->get('email');
        $number = $request->request->get('number');
        $privileges = $request->request->getInt('privileges');
        $userLimit = $request->request->getInt('userLimit');

        if (
            empty($userName)
            || empty($email)
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || ($configuration->get('connectors')['sms'] !== '' && empty($number))
        ) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $userRepository->updateItem(
            (int)$userId,
            $userName,
            $email,
            $number,
            $privileges,
            $userLimit
        );

        return $this->json(
            [
                'message' => 'Details of user ' . $userName . ' updated.'
            ]
        );
    }
}
