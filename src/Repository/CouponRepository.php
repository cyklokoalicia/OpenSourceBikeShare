<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

#@TODO: Refactor the status field to use an enum for better type safety and clarity.
class CouponRepository
{
    public function __construct(private DbInterface $db)
    {
    }

    public function findAllActive(): array
    {
        $coupons = $this->db->query(
            'SELECT coupon, value, status FROM coupons WHERE status=0 ORDER BY status, value, coupon'
        )->fetchAllAssoc();

        return $coupons;
    }

    public function updateStatus(string $coupon, int $status): void
    {
        if (!in_array($status, [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Invalid status value. Must be 0, 1, or 2.');
        }

        $this->db->query(
            'UPDATE coupons SET status = :status WHERE coupon = :coupon LIMIT 1',
            [
                'status' => $status,
                'coupon' => $coupon,
            ]
        );
    }

    public function addItem(string $coupon, float $value): void
    {
        $this->db->query(
            'INSERT INTO coupons (coupon, value, status) VALUES (:coupon, :value, 0)',
            [
                'coupon' => $coupon,
                'value' => $value,
            ]
        );
    }

    public function findActiveItem(string $coupon): ?array
    {
        $result = $this->db->query(
            'SELECT coupon, value, status FROM coupons WHERE coupon = :coupon AND status < 2 LIMIT 1',
            [
                'coupon' => $coupon,
            ]
        );

        return $result->fetchAssoc();
    }
}
