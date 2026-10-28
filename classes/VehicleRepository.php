<?php
declare(strict_types=1);

final class VehicleRepository extends Repository
{
    protected function section(): string
    {
        return 'vehicles';
    }

    protected function idKey(): string
    {
        return 'vehicle_id';
    }

    protected function idPrefix(): string
    {
        return 'V';
    }

    public static function seed(): array
    {
        return SeedData::vehicles();
    }

    /** True when another vehicle (not $exceptId) already uses this plate number. */
    public function plateTaken(string $plate, string $exceptId): bool
    {
        foreach ($this->all() as $vehicle) {
            if (strtoupper((string) ($vehicle['plate_number'] ?? '')) === strtoupper($plate)
                && (string) ($vehicle['vehicle_id'] ?? '') !== $exceptId) {
                return true;
            }
        }
        return false;
    }
}
