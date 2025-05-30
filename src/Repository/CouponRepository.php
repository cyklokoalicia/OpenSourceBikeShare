<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class CouponRepository
{
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function findAllActive(): array
    {
        $coupons = $this->db->query(
            'SELECT coupon, value, status FROM coupons WHERE status=0 ORDER BY status,value,coupon'
        )->fetchAllAssoc();

        return $coupons;
    }

    public function sell(string $coupon): void
    {
        $this->db->query(
            'UPDATE coupons SET status=1 WHERE coupon = :coupon LIMIT 1',
            [
                'coupon' => $coupon,
            ]
        );
    }

    public function addItem(string $coupon, float $value): void
    {
        $coupon = $this->db->escape($coupon);
        $value = $this->db->escape($value);
        $this->db->query(
            'INSERT INTO coupons (coupon, value, status) VALUES (:coupon, :value, 0)',
            [
                'coupon' => $coupon,
                'value' => $value,
            ]
        );
    }
}
