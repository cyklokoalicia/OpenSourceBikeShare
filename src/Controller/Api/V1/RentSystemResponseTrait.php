<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Rent\DTO\RentSystemResult;
use Symfony\Component\HttpFoundation\Response;

trait RentSystemResponseTrait
{
    private function jsonRentSystemResult(RentSystemResult $result): Response
    {
        $status = $result->isError() ? Response::HTTP_CONFLICT : Response::HTTP_OK;
        if ($status < Response::HTTP_BAD_REQUEST) {
            return $this->json($result, $status);
        }

        return $this->json(['detail' => $result->getMessage()], $status);
    }
}
