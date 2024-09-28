<?php

namespace BikeShare\Controller;

use BikeShare\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

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
        Request $request
    ): Response {
        $kernel = $this->kernel;

        ob_start();
        require_once $this->getParameter('kernel.project_dir') . '/command.php';
        $content = ob_get_clean();

        #temporary fix for headers
        $headers = headers_list();
        foreach ($headers as $key => $header) {
            if (strpos($header, 'Location') !== false) {
                return new RedirectResponse('/', 302, $headers);
            }
        }

        return new Response($content);
    }
}
