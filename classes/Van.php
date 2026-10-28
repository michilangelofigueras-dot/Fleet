<?php
declare(strict_types=1);

/** A van: the biggest long-rental discount, 15% from 7 days. Seats 12 unless told otherwise. */
final class Van extends Vehicle
{
    private const LONG_RENT_DAYS = 7;
    private const DISCOUNT       = 0.15;

    public function __construct(array $data)
    {
        if (!isset($data['seating_capacity'])) {
            $data['seating_capacity'] = 12;
        }
        parent::__construct($data);
    }

    public function getCategory(): string
    {
        return 'Van';
    }

    protected function discountRate(int $days): float
    {
        return $days >= self::LONG_RENT_DAYS ? self::DISCOUNT : 0.0;
    }

    public function describe(): string
    {
        return parent::describe() . '  | Van: 15% off from 7 days, group and cargo hire';
    }
}
