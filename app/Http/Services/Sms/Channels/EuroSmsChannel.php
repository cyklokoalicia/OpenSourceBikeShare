<?php

namespace BikeShare\Http\Services\Sms\Channels;

use BikeShare\Http\Services\Sms\EuroSms;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Notification;
use NotificationChannels\Twilio\Exceptions\CouldNotSendNotification;

class EuroSmsChannel
{
    /**
     * @var EuroSms
     */
    protected $euroSms;

    /**
     * @var Dispatcher
     */
    protected $events;

    /**
     * EuroSmsChannel constructor.
     * @param EuroSms $euroSms
     * @param Dispatcher $events
     */
    public function __construct(EuroSms $euroSms, Dispatcher $events)
    {
        $this->euroSms = $euroSms;
        $this->events = $events;
    }

    /**
     * Send the given notification.
     *
     * @param  mixed                                 $notifiable
     * @param \Illuminate\Notifications\Notification $notification
     *
     */
    public function send($notifiable, Notification $notification)
    {
        try {
            $to = $this->getTo($notifiable);
            $message = $notification->toEuroSms($notifiable);

            if (!is_string($message)) {
                throw CouldNotSendNotification::invalidMessageObject($message);
            }

            $this->euroSms->makeRequest($to, $message);
        } catch (Exception $exception) {
            $this->events->fire(
                new NotificationFailed($notifiable, $notification, 'euroSms', ['message' => $exception->getMessage()])
            );
        }
    }

    protected function getTo($notifiable)
    {
        if (isset($notifiable->phone_number)) {
            return $notifiable->phone_number;
        }

        throw CouldNotSendNotification::invalidReceiver();
    }
}
