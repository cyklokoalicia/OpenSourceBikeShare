<?php
namespace Test\Feature\SmsApi;

use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\DbTestCaseWithSeeding;

abstract class BaseSmsTest extends DbTestCaseWithSeeding
{
    use DatabaseMigrations;

    const URL_PREFIX = '/api/sms/receive';

    /**
     * @var SmsRequestContract
     */
    protected $smsRequest;

    /**
     * @var AppConfig
     */
    protected $appConfig;

    protected function setUp()
    {
        parent::setUp();
        $this->smsRequest = app(SmsRequestContract::class);
        $this->appConfig = app(AppConfig::class);
    }

    private function buildSmsUrl($user, $text)
    {
        $getParams = $this->smsRequest->buildGetQuery($text, $user->phone_number,1,1);
        return self::URL_PREFIX . '?' . $getParams;
    }

    protected function sendSms($user, $text)
    {
        return $this->get($this->buildSmsUrl($user, $text));
    }
}
