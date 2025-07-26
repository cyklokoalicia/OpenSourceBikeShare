<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api;

use BikeShare\Repository\UserRepository;
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
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $bikes = $userRepository->findAll();

        return $this->json($bikes);
    }

    /**
     * @Route("/api/user/{userId}", name="api_user_item", methods={"GET"}, requirements: {"userId"="\d+"})
     */
    public function item(
        $userId,
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
        bool $isSmsSystemEnabled,
        Request $request,
        UserRepository $userRepository
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

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
            || ($isSmsSystemEnabled && empty($number))
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
