<?php
declare(strict_types=1);

/** Picks the right Vehicle child class for a category name. */
final class VehicleFactory
{
    public static function create(string $category, array $data): Vehicle
    {
        switch ($category) {
            case 'Car':
                return new Car($data);
            case 'Motorcycle':
                return new Motorcycle($data);
            case 'Van':
                return new Van($data);
        }
        throw new ValidationException('Choose a valid vehicle category.');
    }

    /** Rebuild the object for a saved record. */
    public static function fromRecord(array $record): Vehicle
    {
        $category = isset($record['category']) && is_string($record['category']) ? $record['category'] : 'Car';
        return self::create($category, $record);
    }

    /** A stand-in used only to price a rental whose vehicle record is gone. */
    public static function forPricing(string $category, float $dailyRate): Vehicle
    {
        return self::create($category, [
            'vehicle_id'   => '-',
            'brand'        => 'Unknown',
            'model'        => 'Unknown',
            'plate_number' => '-',
            'daily_rate'   => $dailyRate,
        ]);
    }
}
