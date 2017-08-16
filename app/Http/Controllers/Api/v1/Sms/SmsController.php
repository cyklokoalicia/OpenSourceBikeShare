<?php

namespace BikeShare\Http\Controllers\Api\v1\Sms;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Sms\Sms;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\RentException;
use BikeShare\Http\Services\Rents\Exceptions\RentExceptionType as ER;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Notifications\Sms\BikeAlreadyRented;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\BikeNotTopOfStack;
use BikeShare\Notifications\Sms\BikeRented;
use BikeShare\Notifications\Sms\Credit;
use BikeShare\Notifications\Sms\Free;
use BikeShare\Notifications\Sms\Help;
use BikeShare\Notifications\Sms\InvalidArgumentsCommand;
use BikeShare\Notifications\Sms\RechargeCredit;
use BikeShare\Notifications\Sms\RentLimitExceeded;
use BikeShare\Notifications\Sms\UnknownCommand;
use Dingo\Api\Routing\Helpers;
use Exception;
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

    /**
     * @var StandsRepository
     */
    private $standsRepo;
    /**
     * @var BikesRepository
     */
    private $bikeRepo;

    public function __construct(SmsRequestContract $smsRequest,
                                AppConfig $appConfig,
                                StandsRepository $standsRepository,
                                BikesRepository $bikeRepository)
    {
        $this->smsRequest = $smsRequest;
        $this->appConfig = $appConfig;
        $this->standsRepo = $standsRepository;
        $this->bikeRepo = $bikeRepository;
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
                } else {
                    $this->creditCommand($sms);
                }
                break;
            case "FREE":
                $this->freeCommand($sms);
                break;
            case "RENT":
                if (count($args) < 2){
                    $this->invalidArgumentsCommand($sms, "with bike number: RENT 47");
                } else {
                    $this->rentCommand($sms, $args[1]);
                }
                break;
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

    private function freeCommand($sms)
    {
        $sms->sender->notify(new Free($this->standsRepo));
    }

    private function invalidArgumentsCommand($sms, $errorMsg)
    {
        $sms->sender->notify(new InvalidArgumentsCommand($errorMsg));
    }

    private function rentCommand($sms, $bikeNumber)
    {
        $user = $sms->sender;
        $bikeNumber = intval($bikeNumber);
        if (!$bike = $this->bikeRepo->findByBikeNum($bikeNumber)) {
            $user->notify(new BikeDoesNotExist($bikeNumber));
            return;
        }

        try {
            $rent = app(RentService::class)
                ->rentBike($user, $bike);
            $user->notify(new BikeRented($rent));
        } catch (RentException $e){
            switch ($e->type){
                case ER::LOW_CREDIT:
                    $requiredCredit = $e->param1;
                    $user->notify(new RechargeCredit($this->appConfig,
                        $user->credit,
                        $requiredCredit));
                    break;

                case ER::BIKE_NOT_FREE:
                    if (!$bike->user){
                        throw new Exception("Bike not free but no owner", [$bike->user]);
                    }
                    $user->notify(new BikeAlreadyRented($user, $bike));
                    break;

                case ER::BIKE_NOT_ON_TOP:
                    $topBike = $e->param1;
                    $user->notify(new BikeNotTopOfStack($bike, $topBike));
                    break;

                case ER::MAXIMUM_NUMBER_OF_RENTS:
                    $userLimit = $e->param1;
                    $currentRents = $e->param2;
                    $user->notify(new RentLimitExceeded($userLimit, $currentRents));
                    break;

                default:
                    // unknown type, rethrow
                    throw $e;
            }
        }
    }
}
