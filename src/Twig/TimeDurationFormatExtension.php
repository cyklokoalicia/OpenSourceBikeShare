<?php

declare(strict_types=1);

namespace BikeShare\Twig;

use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class TimeDurationFormatExtension extends AbstractExtension
{
    public function __construct(private TranslatorInterface $translator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('format_duration', [$this, 'formatDuration']),
        ];
    }

    public function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $this->translator->trans('Less than a minute');
        }

        $minutes = round($seconds / 60);
        $hours = floor($minutes / 60);
        $days = floor($hours / 24);

        if ($days > 0) {
            return $this->translator->trans(sprintf('%d days', $days)) . ' ' .
                $this->translator->trans(sprintf('%d hours', $hours - $days * 24));
        }

        if ($hours > 0) {
            return $this->translator->trans(sprintf('%d hours', $hours)) . ' ' .
                $this->translator->trans(sprintf('%d minutes', $minutes - $hours * 60));
        }

        return $this->translator->trans(sprintf('%d minutes', $minutes));
    }
}
