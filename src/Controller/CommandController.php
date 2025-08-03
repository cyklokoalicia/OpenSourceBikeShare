<?php

namespace BikeShare\Controller;

use BikeShare\Repository\StandRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CommandController extends AbstractController
{
    #[Route('/command.php', name: 'command')]
    /**
     * @deprecated PyBikes should use another entry point
     */
    public function index(
        StandRepository $standRepository,
        Request $request,
        LoggerInterface $logger
    ): Response {
        if (
            !is_null($this->getUser())
            || $request->get('action') !== 'map:markers'
        ) {
            return $this->json([], Response::HTTP_BAD_REQUEST);
        }

        $logger->notice(
            'Access to command.php map:markers',
            [
                'ip' => $request->getClientIp(),
                'uri' => $request->getRequestUri(),
                'request' => $request->request->all(),
            ]
        );

        $stands = $standRepository->findAllExtended();

        return $this->json($stands);
    }
}
