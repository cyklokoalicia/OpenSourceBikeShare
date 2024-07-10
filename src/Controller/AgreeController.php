<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AgreeController extends AbstractController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/agree.php", name="agree")
     */
    public function index(
        Request $request
    ): Response {
        $kernel = $this->kernel;

        ob_start();
        require_once $this->getParameter('kernel.project_dir') . '/agree.php';
        $content = ob_get_clean();

        return new Response($content);
    }
}
