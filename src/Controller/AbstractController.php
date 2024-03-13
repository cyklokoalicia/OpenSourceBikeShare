<?php

namespace BikeShare\Controller;

use BikeShare\Authentication\Auth;
use BikeShare\Db\DbInterface;
use BikeShare\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractController implements ControllerInterface
{
    /**
     * @var DbInterface
     */
    protected $db;
    /**
     * @var Request
     */
    protected $request;
    /**
     * @var Auth
     */
    protected $auth;
    /**
     * @var User
     */
    protected $user;
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        Request         $request,
        DbInterface     $db,
        Auth            $auth,
        User            $user,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->db = $db;
        $this->auth = $auth;
        $this->user = $user;
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function checkAccess()
    {
        $this->logRequest();
        $this->auth->refreshSession();

        return true;
    }

    /**
     * @return string
     */
    abstract public function run();

    protected function logRequest()
    {
        $number = 'unknown';
        $userId = $this->auth->getUserId();
        if (!empty($userId)) {
            $number = $this->user->findPhoneNumber($userId);
        }

        $smsText = $this->db->escape($this->request->server->get('REQUEST_URI'));
        $ipAddress = $this->request->server->get('REMOTE_ADDR');

        $this->db->query(
            "INSERT INTO received SET 
                         sender='$number',
                         receive_time='" . date("Y-m-d H:i:s") . "',
                         sms_text='$smsText',
                         ip='$ipAddress'"
        );
        $this->db->commit();
    }
}
