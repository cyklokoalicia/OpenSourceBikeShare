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
    private RentSystemFactory $rentSystemFactory;
    private BikeRepository $bikeRepository;
    private StandRepository $standRepository;
    private TranslatorInterface $translator;

    public function __construct(
        RentSystemFactory $rentSystemFactory,
        BikeRepository $bikeRepository,
        StandRepository $standRepository,
        TranslatorInterface $translator
    ) {
        $this->rentSystemFactory = $rentSystemFactory;
        $this->bikeRepository = $bikeRepository;
        $this->standRepository = $standRepository;
        $this->translator = $translator;
    }

    /**
     * @Route("/scan.php/rent/{bikeNumber}", name="scan_bike", requirements: {"bikeNumber"="\d+"})
     */
    public function rent(
        string $bikeNumber,
        Request $request,
        DbInterface $db
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

            $db->query(
                'INSERT INTO sent 
                SET number = :number,
                text = :text',
                [
                    'number' => $this->getUser()->getUserIdentifier(),
                    'text' => strip_tags($result['message'])
                ]
            );
        }

        return $this->render('scan/rent.html.twig', [
            'bikeNumber' => $bikeNumber,
            'standName' => $bike['standName'],
            'notes' => $bike['notes'],
            'error' => $error ?? null,
            'message' => $message ?? false,
        ]);
    }

    /**
     * @Route("/scan.php/return/{standName}", name="scan_stand", requirements: {"standName"="\w+"})
     */
    public function return(
        string $standName,
        DbInterface $db
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

            $db->query(
                'INSERT INTO sent 
                SET number = :number,
                text = :text',
                [
                    'number' => $this->getUser()->getUserIdentifier(),
                    'text' => strip_tags($result['message'])
                ]
            );
        }

        return $this->render('scan/return.html.twig', [
            'standName' => $standName,
            'error' => $error ?? null,
            'message' => $message ?? false,
        ]);
    }
}
