<?php

declare(strict_types=1);

namespace BikeShare\App\EventListener;

use Symfony\Component\HttpKernel\EventListener\ErrorListener as SymfonyErrorListener;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ErrorListener extends SymfonyErrorListener
{
    protected function logException(\Throwable $exception, string $message, ?string $logLevel = null): void
    {
        if (null !== $this->logger) {
            if (!$exception instanceof HttpExceptionInterface || $exception->getStatusCode() >= 500) {
                $this->logger->critical($message, ['exception' => $exception]);
            } elseif ($exception instanceof NotFoundHttpException) {
                //do not log 404 errors
                return;
            } else {
                $this->logger->error($message, ['exception' => $exception]);
            }
        }
    }
}
