<?php

namespace BikeShare\Credit;

use BikeShare\Db\DbInterface;

class CreditSystemFactory
{
    /**
     * @param array $creditConfiguration
     * @param DbInterface $db
     * @return CreditSystemInterface
     */
    public function getCreditSystem(array $creditConfiguration, DbInterface $db)
    {
        if (!isset($creditConfiguration["enabled"])) {
            $creditConfiguration["enabled"] = false;
        }
        if (!$creditConfiguration["enabled"]) {
            return new DisabledCreditSystem();
        } else {
            return new CreditSystem($creditConfiguration, $db);
        }
    }
}
