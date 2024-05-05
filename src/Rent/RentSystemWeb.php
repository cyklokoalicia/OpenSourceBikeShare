<?php

namespace BikeShare\Rent;

class RentSystemWeb extends AbstractRentSystem implements RentSystemInterface
{
    protected function getRentSystemType() {
        return 'web';
    }

    protected function response($message, $error = 0, $additional = '', $log = 1)
    {
        global $db, $user, $auth;

        $json = array('error' => $error, 'content' => $message);
        if (is_array($additional)) {
            foreach ($additional as $key => $value) {
                $json[$key] = $value;
            }
        }
        $json = json_encode($json);
        if ($log == 1 && $message) {
            $userid = $auth->getUserId();

            $number = $user->findPhoneNumber($userid);
            logresult($number, $message);
        }
        $db->commit();
        echo $json;
        exit;
    }
}