<?php

declare(strict_types=1);

namespace BikeShare\Controller\Api\V1;

use BikeShare\Rent\DTO\RentSystemResult;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

trait RentSystemResponseTrait
{
    private const PROBLEM_TYPE_BLANK = 'about:blank';
    private const PROBLEM_CONTENT_TYPE = 'application/problem+json';

    private function jsonRentSystemResult(RentSystemResult $result, TranslatorInterface $translator): Response
    {
        $rendered = $result->trans($translator);
        $params = (object) $result->getParams();

        if (!$result->isError()) {
            return $this->json(
                [
                    'error' => false,
                    'message' => $rendered,
                    'code' => $result->getCode(),
                    'params' => $params,
                ],
                Response::HTTP_OK
            );
        }

        $status = Response::HTTP_CONFLICT;

        return $this->json(
            [
                'type' => self::PROBLEM_TYPE_BLANK,
                'title' => Response::$statusTexts[$status],
                'status' => $status,
                'detail' => $rendered,
                'code' => $result->getCode(),
                'params' => $params,
            ],
            $status,
            ['Content-Type' => self::PROBLEM_CONTENT_TYPE]
        );
    }
}
