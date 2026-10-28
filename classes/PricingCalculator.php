<?php
declare(strict_types=1);

/** Rental days, late fees and payment status. (Category discounts: Vehicle::calculateRentalCost.) */
final class PricingCalculator
{
    use DateHelpers;

    /** Calendar days between start and expected return, minimum 1. */
    public function rentalDays(string $start, string $end): int
    {
        $span = $this->daysBetween($start, $end);
        return $span > 0 ? $span : 1;
    }

    public function lateDays(string $expectedEnd, string $actualReturn): int
    {
        $span = $this->daysBetween($expectedEnd, $actualReturn);
        return $span > 0 ? $span : 0;
    }

    public function lateFee(float $dailyRate, int $days): float
    {
        return round($dailyRate * Config::LATE_FEE_RATE * max(0, $days), 2);
    }

    public function paymentStatus(float $total, float $paid): string
    {
        if ($paid >= $total && $total > 0) {
            return 'Paid';
        }
        return $paid > 0 ? 'Partial' : 'Unpaid';
    }
}
