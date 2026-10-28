<?php
declare(strict_types=1);

/** Anything that can be hired out and priced. */
interface RentableInterface
{
    public function isAvailable(): bool;

    /** Charges for $days days: ['days', 'subtotal', 'discount', 'total']. */
    public function calculateRentalCost(int $days): array;
}
