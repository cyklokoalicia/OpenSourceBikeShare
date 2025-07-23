<?php

namespace BikeShare\Controller;

use BikeShare\App\Kernel;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CommandController extends AbstractController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/command.php", name="command")
     */
    public function index(
        Request $request,
        LoggerInterface $logger
    ): Response {
        $kernel = $this->kernel;

        if (
            is_null($this->getUser())
            && $request->get('action') !== 'map:markers'
            && $request->headers->get('User-Agent') !== 'PyBikes'
        ) {
            $logger->notice('Access to command.php without authentication', [
                'ip' => $request->getClientIp(),
                'uri' => $request->getRequestUri(),
                'request' => $request->request->all(),
            ]);
        }

        ob_start();
        require $this->getParameter('kernel.project_dir') . '/kernel.php';
        require $this->getParameter('kernel.project_dir') . '/command.php';
        $content = ob_get_clean();

        return new Response($content);
    }
}
