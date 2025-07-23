<?php

namespace BikeShare\Controller;

use BikeShare\App\Kernel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ScanController extends AbstractController
{
    private Kernel $kernel;

    public function __construct(Kernel $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @Route("/scan.php/rent/{bikeNumber}", name="scan_bike", requirements: {"bikeNumber"="\d+"})
     * @Route("/scan.php/return/{standName}", name="scan_stand", requirements: {"standName"="\w+"})
     */
    public function index(
        Request $request
    ): Response {
        $kernel = $this->kernel;

        ob_start();
        require $this->getParameter('kernel.project_dir') . '/kernel.php';
        require $this->getParameter('kernel.project_dir') . '/scan.php';
        $content = ob_get_clean();

        return new Response($content);
    }
}
