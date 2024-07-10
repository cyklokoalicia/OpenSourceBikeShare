<?php

namespace BikeShare\Controller;

use BikeShare\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class RegisterController extends AbstractController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/register.php", name="register")
     */
    public function index(
        Request $request
    ): Response {
        $kernel = $this->kernel;

        ob_start();
        require_once $this->getParameter('kernel.project_dir') . '/register.php';
        $content = ob_get_clean();

        return new Response($content);
    }
}
