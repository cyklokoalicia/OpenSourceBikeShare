<?php
namespace BikeShare\Http\Controllers\Api\v1\Coupons;

use BikeShare\Domain\Coupon\Coupon;
use BikeShare\Domain\Coupon\CouponsRepository;
use BikeShare\Domain\Coupon\CouponStatus;
use BikeShare\Domain\Coupon\CouponTransformer;
use BikeShare\Domain\Coupon\Requests\CreateCouponRequest;
use BikeShare\Http\Controllers\Api\v1\Controller;
use BikeShare\Http\Services\AppConfig;

class CouponsController extends Controller
{

    protected $couponRepo;


    public function __construct(CouponsRepository $couponsRepository)
    {
        $this->couponRepo = $couponsRepository;
    }


    public function index()
    {
        $coupons = $this->couponRepo->all();

        return $this->response->collection($coupons, new CouponTransformer());
    }


    public function store(CreateCouponRequest $request)
    {
        if (! app(AppConfig::class)->isCreditEnabled()) {
            return $this->response->error('Credit is not enabled', 409);
        }

        $value = app(AppConfig::class)->getRequiredCredit() * $request->get('multiplier');

        $count = $request->has('count') ? $request->get('count') : 10;
        $codes = $this->couponRepo->generateCodes($count);

        $duplicates = 0;
        foreach ($codes as $code) {
            if ($this->couponRepo->findBy('coupon', $code)) {
                $duplicates++;
                continue;
            }

            $coupon = new Coupon();
            $coupon->coupon = $code;
            $coupon->value = $value;
            $coupon->status = CouponStatus::FREE;
            $coupon->save();
        }

        return response()->json(['message' => $count - $duplicates . ' coupons was created.']);
    }


    public function show($uuid)
    {
        if (! $coupon = $this->couponRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Coupon not found!');
        }

        return $this->response->item($coupon, new CouponTransformer());
    }


    public function sell($uuid)
    {
        if (! app(AppConfig::class)->isCreditEnabled()) {
            return $this->response->error('Credit is not enabled', 409);
        }

        if (! $coupon = $this->couponRepo->findByUuid($uuid)) {
            return $this->response->errorNotFound('Coupon not found!');
        }

        if ($coupon->status == CouponStatus::SOLD) {
            return $this->response->errorBadRequest('Coupon was sold!');
        }

        $coupon->status = CouponStatus::SOLD;
        $coupon->save();

        return response()->json([]);
    }


    public function validateCoupon($uuid)
    {
        if (! app(AppConfig::class)->isCreditEnabled()) {
            return $this->response->error('Credit is not enabled', 409);
        }

        $coupon = $this->couponRepo->findWhere([
            ['uuid', '=', $uuid],
            ['status', '!=', CouponStatus::VALID]
        ])->first();

        if (! $coupon) {
            return $this->response->errorNotFound('Coupon not found!');
        }

        $this->user->credit = $this->user->credit + $coupon->value;
        $this->user->save();
        // TODO log action

        $coupon->status = CouponStatus::VALID;
        $coupon->save();

        return response()->json([]);
    }
}
