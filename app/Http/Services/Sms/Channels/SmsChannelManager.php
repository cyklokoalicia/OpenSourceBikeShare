<?php
namespace BikeShare\Http\Services\Sms\Channels;

use Illuminate\Foundation\Application;
use Illuminate\Notifications\Notification;
use InvalidArgumentException;
use NotificationChannels\Twilio\TwilioChannel;

class SmsChannelManager
{
    /**
     * The application instance.
     *
     * @var \Illuminate\Foundation\Application
     */
    protected $app;

    protected $availableChannels;

    /**
     * The array of resolved sms drivers.
     *
     * @var array
     */
    protected $channels = [];

    /**
     * Create a new manager instance.
     *
     * @param  \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->availableChannels =[
            'twilio' => TwilioChannel::class,
            'euroSms' => EuroSmsChannel::class,
            'log' => LogSmsChannel::class,
            'null' => NullChannel::class
        ];
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    public function getDefaultChannel()
    {
        return $this->app['config']['bike-share.sms.connector'];
    }

    /**
     * Get a channel instance.
     *
     * @param  string $name
     *
     * @return mixed
     */
    public function channel($name = null)
    {
        $name = $name ?: $this->getDefaultChannel();

        return $this->channels[$name] = $this->get($name);
    }

    protected function get($name)
    {
        return isset($this->channels[$name]) ? $this->channels[$name] : $this->resolve($name);
    }

    /**
     * Resolve the given store.
     *
     * @param  string $name
     *
     * @return \Illuminate\Contracts\Broadcasting\Broadcaster
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve($name)
    {
        if (!array_key_exists($name, $this->availableChannels)) {
            throw new InvalidArgumentException("Sms connector [{$name}] is not defined.");
        }
        return $this->app->make($this->availableChannels[$name]);
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        $this->channel()->send($notifiable, $notification);
    }
}