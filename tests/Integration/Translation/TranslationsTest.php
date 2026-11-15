<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Translation;

use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\TranslatorBagInterface;

class TranslationsTest extends BikeSharingKernelTestCase
{
    private const SOURCE_LOCALE = 'en';
    private const LOCALES = ['en', 'sk', 'cs', 'de', 'uk'];
    private const DOMAINS = ['messages+intl-icu', 'rentSystem+intl-icu', 'validators+intl-icu'];

    /** debug:translation sets this bit of its exit code when a message used in code is not translated. */
    private const DEBUG_TRANSLATION_MISSING = 1;

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

    /**
     * @return iterable<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
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

    #[DataProvider('localeDomainProvider')]
    public function testEveryLocaleTranslatesTheSameMessagesAsTheSource(string $locale, string $domain): void
    {
        $source = $this->messagesOf(self::SOURCE_LOCALE, $domain);
        $messages = $this->messagesOf($locale, $domain);

        $this->assertSame(
            [],
            array_values(array_diff(array_keys($source), array_keys($messages))),
            sprintf('Messages missing in %s / %s', $locale, $domain)
        );
        $this->assertSame(
            [],
            array_values(array_diff(array_keys($messages), array_keys($source))),
            sprintf('Messages in %s / %s that %s does not have', $locale, $domain, self::SOURCE_LOCALE)
        );
        $this->assertSame(
            [],
            array_keys(array_filter(
                $messages,
                static fn (string $message, string $key): bool => $message === '' && $source[$key] !== '',
                ARRAY_FILTER_USE_BOTH
            )),
            sprintf('Empty messages in %s / %s', $locale, $domain)
        );
    }

    #[DataProvider('localeProvider')]
    public function testEveryMessageUsedInCodeIsTranslated(string $locale): void
    {
        $command = (new Application(self::$kernel))->find('debug:translation');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['locale' => $locale, '--only-missing' => true]);

        $this->assertSame(
            0,
            $exitCode & self::DEBUG_TRANSLATION_MISSING,
            sprintf("Messages used in code but missing in %s:\n%s", $locale, $tester->getDisplay())
        );
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
