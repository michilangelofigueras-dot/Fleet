<?php
declare(strict_types=1);

/** A motorcycle: 5% off from just 3 days. Seats 2 unless told otherwise. */
final class Motorcycle extends Vehicle
{
    private const LONG_RENT_DAYS = 3;
    private const DISCOUNT       = 0.05;

    public function __construct(array $data)
    {
        if (!isset($data['seating_capacity'])) {
            $data['seating_capacity'] = 2;
        }
        parent::__construct($data);
    }

    public function getCategory(): string
    {
        return 'Motorcycle';
    }

    protected function discountRate(int $days): float
    {
        return $days >= self::LONG_RENT_DAYS ? self::DISCOUNT : 0.0;
    }

    public function describe(): string
    {
        return parent::describe() . '  | Motorcycle: 5% off from 3 days, helmet required';
    }
}
