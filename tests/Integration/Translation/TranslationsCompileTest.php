<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Translation;

use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Translation\TranslatorBagInterface;

class TranslationsCompileTest extends BikeSharingKernelTestCase
{
    private const LOCALES = ['en', 'sk', 'cs', 'de', 'uk'];
    private const DOMAINS = ['messages+intl-icu', 'rentSystem+intl-icu', 'validators+intl-icu'];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localeDomainProvider(): iterable
    {
        foreach (self::LOCALES as $locale) {
            foreach (self::DOMAINS as $domain) {
                yield $locale . ' ' . $domain => [$locale, $domain];
            }
        }
    }

    #[DataProvider('localeDomainProvider')]
    public function testEveryMessageCompilesAsIcu(string $locale, string $domain): void
    {
        $messages = $this->messagesOf($locale, $domain);
        $this->assertNotEmpty($messages, sprintf('No messages for %s / %s', $locale, $domain));

        foreach ($messages as $key => $pattern) {
            if ($pattern === '') {
                // ICU rejects an empty pattern; the formatter short-circuits it to an empty string.
                continue;
            }

            $formatter = @\MessageFormatter::create($locale, (string)$pattern);
            // The stubs type create() as non-null, but it returns null for an invalid pattern.
            // @phpstan-ignore method.alreadyNarrowedType
            $this->assertNotNull(
                $formatter,
                sprintf(
                    'Translation "%s" in %s / %s is not a valid ICU message: %s',
                    $key,
                    $locale,
                    $domain,
                    intl_get_error_message()
                )
            );
        }
    }

    /**
     * Messages defined by the locale itself, without the fallback locale filling gaps.
     *
     * @return array<string, string>
     */
    private function messagesOf(string $locale, string $domain): array
    {
        $translator = self::getContainer()->get('translator');
        $this->assertInstanceOf(TranslatorBagInterface::class, $translator);

        return $translator->getCatalogue($locale)->all($domain);
    }
}
