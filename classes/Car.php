<?php
declare(strict_types=1);

/** A car: 10% off rentals of 7 days or more. Seats 5 unless told otherwise. */
final class Car extends Vehicle
{
    private const LONG_RENT_DAYS = 7;
    private const DISCOUNT       = 0.10;

    public function __construct(array $data)
    {
        if (!isset($data['seating_capacity'])) {
            $data['seating_capacity'] = 5;
        }
        parent::__construct($data);
    }

    public function getCategory(): string
    {
        return 'Car';
    }

    protected function discountRate(int $days): float
    {
        return $days >= self::LONG_RENT_DAYS ? self::DISCOUNT : 0.0;
    }

    public function describe(): string
    {
        return parent::describe() . '  | Car: 10% off from 7 days';
    }
}
