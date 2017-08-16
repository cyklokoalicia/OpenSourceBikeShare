<?php

namespace BikeShare\Notifications;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RegisterConfirmationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $user;


    /**
     * Create a new notification instance.
     *
     * @param User $user
     */
    public function __construct()
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $ruleUrl = app(AppConfig::class)->getSystemRules();
        $agreeUrl = url('/api/auth/agree/' . $notifiable->confirmation_token);

        return (new MailMessage)
            ->subject('Registration')
            ->greeting("Hello, $notifiable->name!")
            ->line('you have been registered into community bike share system ' .  app(AppConfig::class)->getSystemName())
            ->line('')
            ->line('System rules are available here:')
            ->line($ruleUrl)
            ->line('')
            ->line('By clicking the following link you agree to the System rules:')
            ->action('Agree Rules', $agreeUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
