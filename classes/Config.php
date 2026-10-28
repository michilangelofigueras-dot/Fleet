<?php
declare(strict_types=1);

/**
 * Constants and paths. Per-category discounts live in the Vehicle child classes
 * (Car, Motorcycle, Van); keep the matching values in app.js (the live charge preview) in step.
 */
final class Config
{
    /** "Today" drives Active/Reserved status and late fees; change if you operate elsewhere. */
    public const TIMEZONE = 'Asia/Manila';

    public const LATE_FEE_RATE = 0.2;

    public const VEHICLE_CATEGORIES   = ['Car', 'Motorcycle', 'Van'];
    public const TRANSMISSIONS        = ['Automatic', 'Manual'];
    public const FUEL_TYPES           = ['Gasoline', 'Diesel', 'Electric', 'Hybrid'];
    public const VEHICLE_STATUSES     = ['Available', 'Rented', 'Reserved', 'Maintenance'];
    public const RENTAL_STATUSES      = ['Reserved', 'Active', 'Completed', 'Cancelled'];
    public const LIVE_RENTAL_STATUSES = ['Reserved', 'Active'];
    public const COLLECTIONS          = ['vehicles', 'customers', 'rentals'];

    public static function dataDir(): string
    {
        return dirname(__DIR__) . '/data';
    }

    public static function dataFile(): string
    {
        return self::dataDir() . '/records.json';
    }
}
