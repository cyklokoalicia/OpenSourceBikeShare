<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScanController extends AbstractController
{
    public function __construct(
        private RentSystemFactory $rentSystemFactory,
        private BikeRepository $bikeRepository,
        private StandRepository $standRepository,
        private TranslatorInterface $translator,
        private DbInterface $db,
    ) {
    }

    /**
     * @Route("/scan.php/rent/{bikeNumber}", name="scan_bike", requirements: {"bikeNumber"="\d+"})
     */
    public function rentBike(
        string $bikeNumber,
        Request $request
    ): Response {
        $bikeNumber = (int)$bikeNumber;
        $bike = $this->bikeRepository->findItem($bikeNumber);

        if (empty($bike)) {
            $error = $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNumber]);
        } elseif (empty($bike['standName'])) {
            $error = $this->translator->trans('Bike {bikeNumber} already rented.', ['bikeNumber' => $bikeNumber]);
        } elseif (
            $request->isMethod(Request::METHOD_POST)
            && $request->request->has('rent')
            && $request->request->get('rent') === "yes"
        ) {
            $rentSystem = $this->rentSystemFactory->getRentSystem('qr');
            $result = $rentSystem->rentBike($this->getUser()->getUserId(), $bikeNumber);
            if ($result['error']) {
                $error = $result['message'];
            } else {
                $message = $result['message'];
            }

            $this->logResponse($result['message']);
        }

        return $this->render('scan/rent.html.twig', [
            'bikeNumber' => $bikeNumber,
            'standName' => $bike['standName'] ?? null,
            'notes' => $bike['notes'] ?? null,
            'error' => $error ?? null,
            'message' => $message ?? false,
        ]);
    }

    /**
     * @Route("/scan.php/return/{standName}", name="scan_stand", requirements: {"standName"="\w+"})
     */
    public function returnBike(
        string $standName
    ): Response {
        $stand = $this->standRepository->findItemByName($standName);

        if (empty($stand)) {
            $error = $this->translator->trans('Stand {standName} does not exist.', ['standName' => $standName]);
        } else {
            $rentSystem = $this->rentSystemFactory->getRentSystem('qr');
            $result = $rentSystem->returnBike($this->getUser()->getUserId(), 0, $standName);
            if ($result['error']) {
                $error = $result['message'];
            } else {
                $message = $result['message'];
            }

            $this->logResponse($result['message']);
        }

        return $this->render('scan/return.html.twig', [
            'standName' => $standName,
            'error' => $error ?? null,
            'message' => $message ?? false,
        ]);
    }

    private function logResponse(string $response)
    {
        $this->db->query(
            'INSERT INTO sent 
                SET number = :number,
                text = :text',
            [
                'number' => $this->getUser()->getUserIdentifier(),
                'text' => strip_tags($response)
            ]
        );
    }
}
