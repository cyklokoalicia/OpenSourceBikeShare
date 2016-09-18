<?php
namespace BikeShare\Domain\Coupon;

use BikeShare\Domain\Core\Repository;

class CouponsRepository extends Repository
{

    public function model()
    {
        return Coupon::class;
    }


    public function generateCodes($codesCount = 10)
    {
        $codes = [];
        for ($i = 0; $i < $codesCount; $i++) {
            $codes[] = app(CouponGenerate::class)->generate();
        }

        return $codes;
    }
}
