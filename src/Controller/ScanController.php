<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ScanController extends AbstractController
{
    public function __construct(
        private readonly RentSystemFactory $rentSystemFactory,
        private readonly BikeRepository $bikeRepository,
        private readonly StandRepository $standRepository,
        private readonly TranslatorInterface $translator,
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    public function rentBike(string $bikeNumber, Request $request): Response
    {
        $bikeNumber = (int)$bikeNumber;
        $bike = $this->bikeRepository->findItem($bikeNumber);

        $error = null;
        $result = null;

        if (empty($bike)) {
            $error = new TranslatableMessage('bike.error.not_found', ['bikeNumber' => $bikeNumber]);
        } elseif (empty($bike['standName'])) {
            $error = new TranslatableMessage('bike.error.already_rented_short', ['bikeNumber' => $bikeNumber]);
        } elseif (
            $request->isMethod(Request::METHOD_POST)
            && $request->request->has('rent')
            && $request->request->get('rent') === 'yes'
        ) {
            $rentSystem = $this->rentSystemFactory->getRentSystem(RentSystemType::QR);
            $result = $rentSystem->rentBike($this->getUser()->getUserId(), $bikeNumber);
            $this->logResponse($result);
        }

        return $this->render('scan/rent.html.twig', [
            'bikeNumber' => $bikeNumber,
            'standName' => $bike['standName'] ?? null,
            'notes' => $bike['notes'] ?? null,
            'error' => $error,
            'result' => $result,
        ]);
    }

    public function returnBike(string $standName): Response
    {
        $stand = $this->standRepository->findItemByName($standName);

        $error = null;
        $result = null;

        if (empty($stand)) {
            $error = new TranslatableMessage('stand.error.not_found', ['standName' => $standName]);
        } else {
            $rentSystem = $this->rentSystemFactory->getRentSystem(RentSystemType::QR);
            $result = $rentSystem->returnBike($this->getUser()->getUserId(), 0, $standName);
            $this->logResponse($result);
        }

        return $this->render('scan/return.html.twig', [
            'standName' => $standName,
            'error' => $error,
            'result' => $result,
        ]);
    }

    private function logResponse(TranslatableInterface $response): void
    {
        $this->db->query(
            'INSERT INTO sent
                SET number = :number,
                    text = :text,
                    time = :time
                ',
            [
                'number' => $this->getUser()->getUserIdentifier(),
                'text' => strip_tags($response->trans($this->translator)),
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }
}
