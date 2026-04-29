<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Controller\Api\V1;

use BikeShare\Controller\Api\V1\RentSystemResponseTrait;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class RentSystemResponseTraitTest extends TestCase
{
    public function testSuccessReturnsRenderedShape(): void
    {
        $controller = $this->newController();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (
                string $code,
                array $params = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                $this->assertSame('bike.rent.success', $code);
                $this->assertSame('rentSystem', $domain);
                // Channel is auto-injected from systemType so the translation template can branch
                // on it. Param values are htmlspecialchars-escaped (no HTML wrapping at the PHP
                // level — the translation template embeds the badge spans itself).
                $this->assertSame('web', $params['channel']);
                $this->assertSame('42', $params['bikeNumber']);
                return 'Bike <span class="badge badge-primary">42</span> rented';
            });

        $result = new RentSystemResult(
            false,
            'bike.rent.success',
            RentSystemType::WEB,
            ['bikeNumber' => 42]
        );

        $response = $controller->call($result, $translator);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertSame(false, $data['error']);
        $this->assertSame('Bike <span class="badge badge-primary">42</span> rented', $data['message']);
        $this->assertSame('bike.rent.success', $data['code']);
        $this->assertSame(['bikeNumber' => 42], $data['params']);
    }

    public function testErrorReturnsRfc7807ProblemJson(): void
    {
        $controller = $this->newController();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->willReturnCallback(function (
                string $code,
                array $params = [],
                ?string $domain = null,
                ?string $locale = null,
            ): string {
                $this->assertSame('bike.rent.error.already_rented', $code);
                $this->assertSame('rentSystem', $domain);
                // Scalars are htmlspecialchars-escaped before interpolation on every path
                // (errors included) so user-controlled string params can't smuggle markup
                // through `detail` into .html() / |raw consumers.
                $this->assertSame('1', $params['bikeNumber']);
                return 'Bike 1 is already rented.';
            });

        $result = new RentSystemResult(
            true,
            'bike.rent.error.already_rented',
            RentSystemType::WEB,
            ['bikeNumber' => 1]
        );

        $response = $controller->call($result, $translator);

        $this->assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        $this->assertSame('application/problem+json', $response->headers->get('Content-Type'));

        $data = json_decode($response->getContent(), true);
        $this->assertSame('about:blank', $data['type']);
        $this->assertSame(Response::$statusTexts[Response::HTTP_CONFLICT], $data['title']);
        $this->assertSame(Response::HTTP_CONFLICT, $data['status']);
        $this->assertSame('Bike 1 is already rented.', $data['detail']);
        $this->assertSame('bike.rent.error.already_rented', $data['code']);
        $this->assertSame(['bikeNumber' => 1], $data['params']);
    }

    public function testEmptyParamsAreSerializedAsObject(): void
    {
        $controller = $this->newController();
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturn('rendered');

        $result = new RentSystemResult(false, 'bike.rent.error.zero_limit', RentSystemType::SMS);

        $response = $controller->call($result, $translator);

        // Verify params is rendered as `{}` (object) rather than `[]` (array) for client-side type safety.
        $this->assertStringContainsString('"params":{}', $response->getContent());
    }

    private function newController(): object
    {
        return new class {
            use RentSystemResponseTrait;

            public function call(RentSystemResult $result, TranslatorInterface $translator): JsonResponse
            {
                return $this->jsonRentSystemResult($result, $translator);
            }

            public function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
            {
                return new JsonResponse($data, $status, $headers);
            }
        };
    }
}
