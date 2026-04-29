<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Translation;

use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Verifies that ICU-formatted translation templates render correctly for composite cases —
 * messages built from a base + optional "appendix" sections via {flag, select, true {...} other {}}.
 */
class IcuTemplateTest extends BikeSharingKernelTestCase
{
    private TranslatorInterface $translator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translator = self::getContainer()->get(TranslatorInterface::class);
    }

    public function testBikeRentSuccessWithoutNote(): void
    {
        $rendered = $this->translator->trans('bike.rent.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'currentCode' => '1234',
            'newCode' => '5678',
            'hasNote' => 'false',
            'note' => '',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('Bike 42', $rendered);
        $this->assertStringContainsString('1234', $rendered);
        $this->assertStringContainsString('5678', $rendered);
        $this->assertStringNotContainsString('Reported issue', $rendered);
    }

    public function testBikeRentSuccessWithNote(): void
    {
        $rendered = $this->translator->trans('bike.rent.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'currentCode' => '1234',
            'newCode' => '5678',
            'hasNote' => 'true',
            'note' => 'Flat tire',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('Bike 42', $rendered);
        $this->assertStringContainsString('Reported issue: Flat tire', $rendered);
    }

    public function testBikeReturnSuccessNoNoteNoCreditChange(): void
    {
        $rendered = $this->translator->trans('bike.return.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'standName' => 'STAND1',
            'currentCode' => '1234',
            'hasNote' => 'false',
            'note' => '',
            'hasCreditChange' => 'false',
            'creditChange' => 0,
            'creditCurrency' => 'EUR',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('Bike 42', $rendered);
        $this->assertStringContainsString('STAND1', $rendered);
        $this->assertStringNotContainsString('reported this problem', $rendered);
        $this->assertStringNotContainsString('Credit change', $rendered);
    }

    public function testBikeReturnSuccessWithNoteOnly(): void
    {
        $rendered = $this->translator->trans('bike.return.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'standName' => 'STAND1',
            'currentCode' => '1234',
            'hasNote' => 'true',
            'note' => 'Broken chain',
            'hasCreditChange' => 'false',
            'creditChange' => 0,
            'creditCurrency' => 'EUR',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('reported this problem: Broken chain.', $rendered);
        $this->assertStringNotContainsString('Credit change', $rendered);
    }

    public function testBikeReturnSuccessWithCreditChangeOnly(): void
    {
        $rendered = $this->translator->trans('bike.return.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'standName' => 'STAND1',
            'currentCode' => '1234',
            'hasNote' => 'false',
            'note' => '',
            'hasCreditChange' => 'true',
            'creditChange' => 5,
            'creditCurrency' => 'EUR',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('Credit change: -5EUR.', $rendered);
        $this->assertStringNotContainsString('reported this problem', $rendered);
    }

    public function testBikeReturnSuccessWithBothAppendixBlocks(): void
    {
        $rendered = $this->translator->trans('bike.return.success', [
            'channel' => 'sms',
            'bikeNumber' => 42,
            'standName' => 'STAND1',
            'currentCode' => '1234',
            'hasNote' => 'true',
            'note' => 'Broken chain',
            'hasCreditChange' => 'true',
            'creditChange' => 5,
            'creditCurrency' => 'EUR',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('Bike 42', $rendered);
        $this->assertStringContainsString('reported this problem: Broken chain.', $rendered);
        $this->assertStringContainsString('Credit change: -5EUR.', $rendered);
    }

    public function testCommandFreeMessageNoBikes(): void
    {
        $rendered = $this->translator->trans('command.free.message', [
            'hasBikes' => 'false',
            'bikesList' => '',
            'hasEmptyStands' => 'false',
            'standsList' => '',
        ], null, 'en');

        $this->assertSame('No free bikes.', trim($rendered));
    }

    public function testCommandFreeMessageBikesAndEmptyStands(): void
    {
        $rendered = $this->translator->trans('command.free.message', [
            'hasBikes' => 'true',
            'bikesList' => "Main: 3\nPark: 1",
            'hasEmptyStands' => 'true',
            'standsList' => "Central\nEastEnd",
        ], null, 'en');

        $this->assertStringContainsString('Free bikes counts:', $rendered);
        $this->assertStringContainsString('Main: 3', $rendered);
        $this->assertStringContainsString('Park: 1', $rendered);
        $this->assertStringContainsString('Empty stands:', $rendered);
        $this->assertStringContainsString('Central', $rendered);
        $this->assertStringContainsString('EastEnd', $rendered);
    }

    public function testReturnMultipleRentedBikesIcuSelectOnHasSms(): void
    {
        $withSms = $this->translator->trans('bike.return.error.multiple_rented_bikes', [
            'bikeNumber' => 3,
            'hasSms' => 'true',
        ], 'rentSystem', 'en');
        $withoutSms = $this->translator->trans('bike.return.error.multiple_rented_bikes', [
            'bikeNumber' => 3,
            'hasSms' => 'false',
        ], 'rentSystem', 'en');

        $this->assertStringContainsString('web or SMS', $withSms);
        $this->assertStringContainsString('web', $withoutSms);
        $this->assertStringNotContainsString('SMS', $withoutSms);
    }
}
