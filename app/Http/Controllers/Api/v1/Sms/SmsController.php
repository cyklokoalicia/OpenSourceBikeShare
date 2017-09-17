<?php

namespace BikeShare\Http\Controllers\Api\v1\Sms;

use BikeShare\Domain\Bike\BikesRepository;
use BikeShare\Domain\Sms\Sms;
use BikeShare\Domain\Stand\StandsRepository;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Http\Services\Rents\Exceptions\BikeDoesNotExistException;
use BikeShare\Http\Services\Rents\Exceptions\StandDoesNotExistException;
use BikeShare\Http\Services\Rents\RentService;
use BikeShare\Http\Services\Sms\Receivers\SmsRequestContract;
use BikeShare\Http\Services\Sms\SmsCommand;
use BikeShare\Http\Services\Sms\SmsUtils;
use BikeShare\Notifications\Sms\BikeDoesNotExist;
use BikeShare\Notifications\Sms\StandDoesNotExist;
use BikeShare\Notifications\Sms\Unauthorized;
use Dingo\Api\Routing\Helpers;
use Illuminate\Auth\Access\AuthorizationException;
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

    /**
     * @var RentService
     */
    private $rentService;

    public function __construct()
    {
        $this->smsRequest = app(SmsRequestContract::class);
        $this->appConfig = app(AppConfig::class);
        $this->standsRepo = app(StandsRepository::class);
        $this->bikeRepo = app(BikesRepository::class);
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

        $args = SmsUtils::parseSmsArguments($sms->sms_text);
        $command = $args[0];

        $smsCommand = SmsCommand::by($sms->sender);

        try {
            switch($command)
            {
                case "HELP":
                    $smsCommand->help();
                    break;

                case "CREDIT":
                    if (!$this->appConfig->isCreditEnabled()){
                        $smsCommand->unknown($command);
                    } else {
                        $smsCommand->credit();
                    }
                    break;

                case "FREE":
                    $smsCommand->free();
                    break;

                case "RENT":
                    if (count($args) < 2){
                        $smsCommand->invalidArguments("with bike number: RENT 47");
                    } else {
                        $smsCommand->rentBike($this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;

                case "FORCERENT":
                    if (count($args) < 2){
                        $smsCommand->invalidArguments("with bike number: FORCERENT 47");
                    } else {
                        $smsCommand->forceRentBike($this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;

                case "RETURN":
                    if (count($args) < 3){
                        $smsCommand->invalidArguments( "with bike number and stand name: RENT 47 RACKO");
                    } else {
                        $smsCommand->returnBike($this->bikeRepo->getBikeOrFail($args[1]), $this->standsRepo->getStandOrFail($args[2]));
                    }
                    break;

                case "FORCERETURN":
                    if (count($args) < 3){
                        $smsCommand->invalidArguments( "with bike number and stand name: FORCERETURN 47 RACKO");
                    } else {
                        $smsCommand->forceReturnBike($this->bikeRepo->getBikeOrFail($args[1]), $this->standsRepo->getStandOrFail($args[2]));
                    }
                    break;

                case "WHERE":
                case "WHO":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with bike number: WHERE 47");
                    } else {
                        $smsCommand->whereIsBike($this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;

                case "INFO":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with stand name: INFO RACKO");
                    } else {
                        $smsCommand->standInfo($this->standsRepo->getStandOrFail($args[1]));
                    }
                    break;

                case "NOTE":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( 'with bike number/stand name and problem description: NOTE 47 Flat tire on front wheel');
                    } else {
                        $smsCommand->note($args[1], SmsUtils::parseNoteFromSms($sms->sms_text, $command));
                    }
                    break;

                case "TAG":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( 'with stand name and problem description: TAG MAINSQUARE vandalism');
                    } else {
                        $smsCommand->tag($this->standsRepo->getStandOrFail($args[1]), SmsUtils::parseNoteFromSms($sms->sms_text, $command));
                    }
                    break;

                case "DELNOTE":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with bike number/stand name and optional pattern. All messages or notes matching pattern will be deleted: DELNOTE 47 wheel");
                    } else {
                        $smsCommand->deleteNote($args[1], SmsUtils::parseNoteFromSms($sms->sms_text, $command));
                    }
                    break;


                case "UNTAG":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with stand name and optional pattern. All notes matching pattern will be deleted for all bikes on that stand: UNTAG SAFKO1 pohoda");
                    } else {
                        $smsCommand->untag($this->standsRepo->getStandOrFail($args[1]), SmsUtils::parseNoteFromSms($sms->sms_text, $command));
                    }
                    break;

                case "LIST":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with stand name: LIST RACKO");
                    } else {
                        $smsCommand->listBikes($this->standsRepo->getStandOrFail($args[1]));
                    }
                    break;

                case "REVERT":
                    if (count($args) < 2) {
                        $smsCommand->invalidArguments( "with bike number: REVERT 47");
                    } else {
                        $smsCommand->revert($this->bikeRepo->getBikeOrFail($args[1]));
                    }
                    break;
//            case "LAST":
//                checkUserPrivileges($sms->Number());
//                validateReceivedSMS($sms->Number(),count($args),2,_('with bike number:')." LAST 47");
//                last($sms->Number(),$args[1]);
//                break;
                default:
                    $smsCommand->unknown($command);
                    break;
            }

        }
        catch (BikeDoesNotExistException $e)
        {
            $sms->sender->notify(new BikeDoesNotExist($e->bikeNumber));
        }
        catch (StandDoesNotExistException $e)
        {
            $sms->sender->notify(new StandDoesNotExist($e->standName));
        }
        catch (AuthorizationException $e)
        {
            $sms->sender->notify(new Unauthorized);
        }
    }
}
