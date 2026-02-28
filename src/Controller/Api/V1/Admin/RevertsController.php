<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1\Admin;

use BikeShare\Controller\Api\V1\RentSystemResponseTrait;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RevertsController extends AbstractController
{
    use RentSystemResponseTrait;

    public function create(Request $request, RentSystemFactory $rentSystemFactory): Response
    {
        $payload = $request->getPayload()->all();
        $bikeNumber = isset($payload['bikeNumber']) && is_numeric($payload['bikeNumber'])
            ? (int)$payload['bikeNumber']
            : null;
        if ($bikeNumber === null) {
            return $this->json(['detail' => 'bikeNumber is required'], Response::HTTP_BAD_REQUEST);
        }

        $response = $rentSystemFactory->getRentSystem(RentSystemType::WEB)->revertBike(
            $this->getUser()->getUserId(),
            $bikeNumber,
        );

        return $this->jsonRentSystemResult($response);
    }
}
