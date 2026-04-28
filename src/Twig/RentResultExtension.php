<?php

declare(strict_types=1);

namespace BikeShare\Twig;

use BikeShare\Translation\TranslatableResult;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class RentResultExtension extends AbstractExtension
{
    // Keys rendered as <span class="badge"> when translating to HTML; others are escaped only.
    private const DECORATED_PARAMS = ['bikeNumber', 'currentCode', 'newCode', 'standName', 'code'];

    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('rent_result_html', $this->renderHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderHtml(TranslatableResult $result): string
    {
        $params = $result->getParams();
        foreach ($params as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $escaped = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            $shouldDecorate = in_array($key, self::DECORATED_PARAMS, true)
                && $value !== ''
                && $value !== null;
            $params[$key] = $shouldDecorate
                ? sprintf('<span class="badge badge-primary">%s</span>', $escaped)
                : $escaped;
        }

        $rendered = $this->translator->trans($result->getCode(), $params);

        return nl2br($rendered);
    }
}
