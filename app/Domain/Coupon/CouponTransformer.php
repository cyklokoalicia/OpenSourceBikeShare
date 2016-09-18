<?php
namespace BikeShare\Domain\Coupon;

use BikeShare\Domain\User\UserTransformer;
use League\Fractal\TransformerAbstract;

class CouponTransformer extends TransformerAbstract
{

    public $availableIncludes = ['user'];


    public function transform(Coupon $coupon)
    {
        return [
            'uuid'       => $coupon->uuid,
            'coupon'     => $coupon->coupon,
            'value'      => $coupon->value,
            'status'     => $coupon->status,
            'created_at' => (string)$coupon->created_at,
        ];
    }


    public function includeUser(Coupon $coupon)
    {
        if ($user = $coupon->user) {
            return $this->item($user, new UserTransformer());
        }

        return null;
    }
}
