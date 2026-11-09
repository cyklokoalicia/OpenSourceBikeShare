<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Translation;

use BikeShare\Notification\NotificationType;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class NotificationSubjectTranslationTest extends BikeSharingKernelTestCase
{
    #[DataProvider('localeProvider')]
    public function testEveryTypeHasASubjectInTheLocale(string $locale): void
    {
        $translator = self::getContainer()->get(TranslatorInterface::class);
        $this->assertInstanceOf(TranslatorBagInterface::class, $translator);
        $catalogue = $translator->getCatalogue($locale);

        foreach (NotificationType::cases() as $type) {
            // defines() ignores the fallback locale, so a missing key cannot hide behind English.
            $this->assertTrue(
                $catalogue->defines($type->subjectKey(), 'messages'),
                sprintf('Missing %s subject "%s"', $locale, $type->subjectKey())
            );
            $this->assertNotSame('', $catalogue->get($type->subjectKey(), 'messages'));
        }
    }

    public static function localeProvider(): iterable
    {
        foreach (['en', 'sk', 'cs', 'de', 'uk'] as $locale) {
            yield $locale => [$locale];
        }
    }
}
