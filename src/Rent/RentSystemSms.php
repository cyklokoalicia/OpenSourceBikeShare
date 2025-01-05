<?php

namespace BikeShare\Rent;

use BikeShare\Authentication\Auth;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RentSystemSms extends AbstractRentSystem implements RentSystemInterface
{
    private $number;
    /**
     * @var SmsSenderInterface
     */
    private $smsSender;

    public function __construct(
        SmsSenderInterface $smsSender,
        DbInterface $db,
        CreditSystemInterface $creditSystem,
        User $user,
        Auth $auth,
        EventDispatcherInterface $eventDispatcher,
        LoggerInterface $logger,
        array $watchesConfig,
        array $connectorsConfig,
        $forceStack = false
    ) {
        parent::__construct(
            $db,
            $creditSystem,
            $user,
            $auth,
            $eventDispatcher,
            $logger,
            $watchesConfig,
            $connectorsConfig,
            $forceStack
        );
        $this->smsSender = $smsSender;
    }


    public function rentBike($number, $bikeId, $force = false)
    {
        $this->number = $number;
        $userId = $this->user->findUserIdByNumber($number);
        if (is_null($userId)) {
            $this->logger->error("Invalid number", ["number" => $number]);
            //currently do nothing
            //return $this->response(_('Your number is not registered.'), ERROR);

            return;
        }

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike($number, $bikeId, $standName, $note = '', $force = false)
    {
        $this->number = $number;
        $userId = $this->user->findUserIdByNumber($number);

        if (is_null($userId)) {
            $this->logger->error("Invalid number", ["number" => $number, 'sms' => $note]);
            //currently do nothing
            //return $this->response(_('Your number is not registered.'), ERROR);

            return;
        }

        if (preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $note, $matches)) {
            $note = $this->db->escape(trim($matches[1]));
        } else {
            $note = '';
        }

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    public function revertBike($number, $bikeId)
    {
        $this->number = $number;
        $userId = $this->user->findUserIdByNumber($number);

        if (is_null($userId)) {
            $this->logger->error("Invalid number", ["number" => $number]);
            //currently do nothing
            //return $this->response(_('Your number is not registered.'), ERROR);

            return;
        }

        return parent::revertBike($userId, $bikeId);
    }

    public static function getType(): string
    {
        return 'sms';
    }

    protected function response($message, $error = 0)
    {
        $this->smsSender->send($this->number, strip_tags($message));
    }
}
