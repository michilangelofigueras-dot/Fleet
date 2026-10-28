<?php
declare(strict_types=1);

final class RentalRepository extends Repository
{
    protected function section(): string
    {
        return 'rentals';
    }

    protected function idKey(): string
    {
        return 'rental_id';
    }

    protected function idPrefix(): string
    {
        return 'R';
    }

    protected function idWidth(): int
    {
        return 4;
    }

    public static function seed(): array
    {
        return SeedData::rentals();
    }

    public function countForVehicle(string $vehicleId): int
    {
        return $this->countWhere('vehicle_id', $vehicleId);
    }

    public function countForCustomer(string $customerId): int
    {
        return $this->countWhere('customer_id', $customerId);
    }

    private function countWhere(string $key, string $value): int
    {
        $count = 0;
        foreach ($this->all() as $rental) {
            if ((string) ($rental[$key] ?? '') === $value) {
                $count++;
            }
        }
        return $count;
    }
}
