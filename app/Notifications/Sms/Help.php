<?php

namespace BikeShare\Notifications\Sms;

use BikeShare\Domain\User\User;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\SmsNotification;

class Help extends SmsNotification
{
    private $isCreditEnabled;
    /**
     * @var User
     */
    private $user;

    public function __construct(User $user, AppConfig $appConfig)
    {
        $this->isCreditEnabled = $appConfig->isCreditEnabled();
        $this->user = $user;
    }

    public function smsText()
    {
        $message="Commands:\nHELP\n";
        if ($this->isCreditEnabled) {
            $message.="CREDIT\n";
        }
        if ($this->user->hasRole('admin')){
            $message.="FREE\nRENT bikenumber\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem\n---\nFORCERENT bikenumber\nFORCERETURN bikeno stand\nLIST stand\nLAST bikeno\nREVERT bikeno\nADD email phone fullname\nDELNOTE bikeno [pattern]\nTAG stand note for all bikes\nUNTAG stand [pattern]";
        } else {
            $message.="FREE\nRENT bikeno\nRETURN bikeno stand\nWHERE bikeno\nINFO stand\nNOTE bikeno problem description\nNOTE stand problem description";
        }
        return $message;
    }
}
