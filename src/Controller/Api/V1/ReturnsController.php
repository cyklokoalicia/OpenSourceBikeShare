<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ReturnsController extends AbstractController
{
    use RentSystemResponseTrait;

    public function create(Request $request, RentSystemFactory $rentSystemFactory): Response
    {
        $payload = $request->getPayload()->all();
        $bikeNumber = isset($payload['bikeNumber']) && is_numeric($payload['bikeNumber'])
            ? (int)$payload['bikeNumber']
            : null;
        $standName = isset($payload['standName']) && is_string($payload['standName'])
            ? trim($payload['standName'])
            : '';

        if ($bikeNumber === null || $standName === '') {
            return $this->json(
                ['detail' => 'bikeNumber and standName are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $note = isset($payload['note']) && is_string($payload['note']) ? $payload['note'] : '';
        $response = $rentSystemFactory->getRentSystem(RentSystemType::WEB)->returnBike(
            $this->getUser()->getUserId(),
            $bikeNumber,
            $standName,
            $note
        );

        return $this->jsonRentSystemResult($response);
    }
}
