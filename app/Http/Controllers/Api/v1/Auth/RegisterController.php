<?php
namespace BikeShare\Http\Controllers\Api\v1\Auth;

use BikeShare\Domain\Auth\Requests\RegisterRequest;
use BikeShare\Domain\Auth\Requests\VerifyPhoneNumberRequest;
use BikeShare\Domain\User\Events\UserWasRegistered;
use BikeShare\Domain\User\User;
use BikeShare\Domain\User\UsersRepository;
use BikeShare\Domain\User\UserTransformer;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;
use BikeShare\Notifications\RegisterConfirmationNotification;
use Illuminate\Http\Request;

class RegisterController extends Controller
{

    protected $userRepo;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->userRepo = $usersRepository;
    }

    public function verifyPhoneNumber(VerifyPhoneNumberRequest $request)
    {
        $phone_number = $request->only('phone_number');
        $smsToken = mt_rand(100000, 999999);

        return response([
            'phone_number' => $phone_number['phone_number'],
            'sms' => $smsToken
        ]);
        // TODO send sms by sms service
    }


    /**
     * Handle a registration request for the application.
     *
     * @param RegisterRequest|Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function register(RegisterRequest $request)
    {
        $user = $this->create($request->all());
        $user->notify(new RegisterConfirmationNotification());
        event(new UserWasRegistered($user));

        return $this->response->item($user, new UserTransformer());
    }


    /**
     * @param $token
     *
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response|void
     */
    public function agree($token)
    {
        if (! $user = $this->userRepo->findBy('confirmation_token', $token)) {
            return $this->response->errorBadRequest('Bad token');
        }
        $user->limit = app(AppConfig::class)->getRegistrationLimits();
        $user->confirmation_token = null;
        $user->save();

        return response()->json(['message' => 'Your account has been activated.']);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'password' => bcrypt($data['password']),
            'confirmation_token' => $this->userRepo->getConfirmationToken(),
            'credit' => 0,
            'locked' => 1,
        ]);
    }
}
