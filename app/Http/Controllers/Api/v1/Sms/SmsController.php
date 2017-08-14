<?php

namespace BikeShare\Http\Controllers\Api\v1\Sms;

use BikeShare\Domain\Sms\Sms;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\UnknownCommand;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Validator;

class SmsController extends Controller
{
    use Helpers;

    /**
     * @var SmsRequestContract
     */
    private $smsRequest;

    /**
     * @var AppConfig
     */
    private $appConfig;

    public function __construct(SmsRequestContract $smsRequest, AppConfig $appConfig)
    {
        $this->smsRequest = $smsRequest;
        $this->appConfig = $appConfig;
    }

    public function receive(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            $this->smsRequest->rules()
        );

        if ($validator->fails()){
            $errorMsg = implode(' ', $validator->messages()->all());
            $this->response->errorBadRequest($errorMsg);
        }

        $receivedSms = $this->smsRequest->smsModel($request);

        if (!$receivedSms->sender){
            activity()
                ->withProperties($request->all())
                ->log("Sms from unregistered number");
            $this->response->errorBadRequest('Unregistered number');
        }

        $receivedSms->save();
        $this->parseCommand($receivedSms);
        return $this->response->noContent();
    }

    protected function parseCommand(Sms $sms)
    {
        //preg_split must be used instead of explode because of multiple spaces
        $args = preg_split("/\s+/", strtoupper(trim(urldecode($sms->sms_text))));

        switch($args[0])
        {
            case "HELP":
                $this->helpCommand($sms);
                break;
            case "CREDIT":
                if (!$this->appConfig->isCreditEnabled()){
                    $this->unknownCommand($sms, $args[0]);
                    break;
                }
                $this->creditCommand($sms);
                break;
//            case "FREE":
//                freeBikes($sms->Number());
//                break;
//            case "RENT":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." RENT 47");
//                rent($sms->Number(),$args[1]);//intval
//                break;
//            case "RETURN":
//                validateReceivedSMS($sms->Number(),count($args),3,_('with bike number and stand name:')." RETURN 47 RACKO");
//                returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
//                break;
//            case "FORCERENT":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." FORCERENT 47");
//                rent($sms->Number(),$args[1],TRUE);
//                break;
//            case "FORCERETURN":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),3,_('with bike number and stand name:')." FORCERETURN 47 RACKO");
//                returnBike($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())),TRUE);
//                break;
//            case "WHERE":
//            case "WHO":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." WHERE 47");
//                where($sms->Number(),$args[1]);
//                break;
//            case "INFO":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name:')." INFO RACKO");
//                info($sms->Number(),$args[1]);
//                break;
//            case "NOTE":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number/stand name and problem description:')." NOTE 47 "._('Flat tire on front wheel'));
//                note($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "TAG":
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name and problem description:')." TAG MAINSQUARE "._('vandalism'));
//                tag($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "DELNOTE":
//                validateReceivedSMS($sms->Number(),count($args),1,_('with bike number and optional pattern. All messages or notes matching pattern will be deleted:')." NOTE 47 wheel");
//                delnote($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "UNTAG":
//                validateReceivedSMS($sms->Number(),count($args),1,_('with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand:')." UNTAG SAFKO1 pohoda");
//                untag($sms->Number(),$args[1],trim(urldecode($sms->Text())));
//                break;
//            case "LIST":
//                //checkUserPrivileges($sms->Number()); //allowed for all users as agreed
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with stand name:')." LIST RACKO");
//                validateReceivedSMS($sms->Number(),count($args),2,"with stand name: LIST RACKO");
//                listBikes($sms->Number(),$args[1]);
//                break;
//            case "ADD":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),3,_('with email, phone, fullname:')." ADD king@earth.com 0901456789 Martin Luther King Jr.");
//                add($sms->Number(),$args[1],$args[2],trim(urldecode($sms->Text())));
//                break;
//            case "REVERT":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." REVERT 47");
//                revert($sms->Number(),$args[1]);
//                break;
//            //    case "NEAR":
//            //    case "BLIZKO":
//            //	near($sms->Number(),$args[1]);
//            case "LAST":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." LAST 47");
//                last($sms->Number(),$args[1]);
//                break;
            default:
                $this->unknownCommand($sms, $args[0]);
                break;
        }
    }

    private function helpCommand(Sms $sms)
    {
        $sms->sender->notify(new Help($this->appConfig));
    }

    private function unknownCommand(Sms $sms, $command)
    {
        $sms->sender->notify(new UnknownCommand($command));
    }

    private function creditCommand(Sms $sms)
    {
        $sms->sender->notify(new Credit($this->appConfig, $sms->sender));
    }
}
