<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Translation;

use BikeShare\Translation\PlainText;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class PlainTextTest extends TestCase
{
    public function testReturnsTextUnchanged(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Hello {name}' => 'Something else'], 'sk', 'messages+intl-icu');

        $this->assertSame('Hello {name}', (new PlainText('Hello {name}'))->trans($translator, 'sk'));
    }
}
