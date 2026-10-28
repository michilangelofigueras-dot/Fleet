<?php
declare(strict_types=1);

/**
 * Abstract parent of Car, Motorcycle and Van.
 *
 * - Properties are private; the only way in is a setter, and every setter validates
 *   (encapsulation) and throws ValidationException on bad data.
 * - Subclasses must supply getCategory() and discountRate() (abstract methods), so the same
 *   calculateRentalCost() call prices differently depending on the real object (polymorphism).
 */
abstract class Vehicle implements RentableInterface, ExportableInterface
{
    use TimestampTrait;
    use ValidatesInput;

    private $vehicleId = '';
    private $brand = '';
    private $model = '';
    private $year = 0;
    private $plateNumber = '';
    private $transmission = 'Automatic';
    private $fuelType = 'Gasoline';
    private $seatingCapacity = 1;
    private $dailyRate = 0.0;
    private $status = 'Available';
    private $description = '';

    /** Builds a vehicle from a record or form input; throws ValidationException when invalid. */
    public function __construct(array $data)
    {
        $transmission = $this->inputText($data, 'transmission');
        $fuel         = $this->inputText($data, 'fuel_type');

        $this->setVehicleId($this->inputText($data, 'vehicle_id'));
        $this->setBrand($this->inputText($data, 'brand'));
        $this->setModel($this->inputText($data, 'model'));
        $this->setYear((int) ($data['year'] ?? 0));
        $this->setPlateNumber($this->inputText($data, 'plate_number'));
        $this->setTransmission(in_array($transmission, Config::TRANSMISSIONS, true) ? $transmission : 'Automatic');
        $this->setFuelType(in_array($fuel, Config::FUEL_TYPES, true) ? $fuel : 'Gasoline');
        $this->setSeatingCapacity((int) ($data['seating_capacity'] ?? 1));
        $this->setDailyRate((float) ($data['daily_rate'] ?? 0));
        $this->setStatus($this->inputText($data, 'status', 'Available'));
        $this->setDescription($this->inputText($data, 'description'));

        $created = $data['created_at'] ?? null;
        $updated = $data['updated_at'] ?? null;
        $this->setCreatedAt(is_string($created) ? $created : null);
        $this->setUpdatedAt(is_string($updated) ? $updated : null);
    }

    /* ---- abstract: every child must decide these for itself ---- */

    /** "Car", "Motorcycle" or "Van". */
    abstract public function getCategory(): string;

    /** Fraction taken off the subtotal for a rental of $days days (0.10 = 10%). */
    abstract protected function discountRate(int $days): float;

    /* ---- RentableInterface ---- */

    public function isAvailable(): bool
    {
        return $this->status === 'Available';
    }

    public function calculateRentalCost(int $days): array
    {
        $days     = max(1, $days);
        $subtotal = $this->dailyRate * $days;
        $discount = $subtotal * $this->discountRate($days);

        return [
            'days'     => $days,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'total'    => round($subtotal - $discount, 2),
        ];
    }

    /* ---- ExportableInterface ---- */

    public function toArray(): array
    {
        $record = [
            'vehicle_id'       => $this->vehicleId,
            'brand'            => $this->brand,
            'model'            => $this->model,
            'year'             => $this->year,
            'plate_number'     => $this->plateNumber,
            'category'         => $this->getCategory(),
            'transmission'     => $this->transmission,
            'fuel_type'        => $this->fuelType,
            'seating_capacity' => $this->seatingCapacity,
            'daily_rate'       => $this->dailyRate,
            'status'           => $this->status,
            'description'      => $this->description,
        ];
        if ($this->getCreatedAt() !== null) {
            $record['created_at'] = $this->getCreatedAt();
        }
        if ($this->getUpdatedAt() !== null) {
            $record['updated_at'] = $this->getUpdatedAt();
        }
        return $record;
    }

    /** One-line summary. Children override this and extend it with parent::describe(). */
    public function describe(): string
    {
        return sprintf(
            '%s  %s %s (%d)  %s  %s/day',
            $this->vehicleId,
            $this->brand,
            $this->model,
            $this->year,
            $this->plateNumber,
            $this->money($this->dailyRate)
        );
    }

    protected function money(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    /* ---- getters ---- */

    public function getVehicleId(): string { return $this->vehicleId; }
    public function getBrand(): string { return $this->brand; }
    public function getModel(): string { return $this->model; }
    public function getYear(): int { return $this->year; }
    public function getPlateNumber(): string { return $this->plateNumber; }
    public function getTransmission(): string { return $this->transmission; }
    public function getFuelType(): string { return $this->fuelType; }
    public function getSeatingCapacity(): int { return $this->seatingCapacity; }
    public function getDailyRate(): float { return $this->dailyRate; }
    public function getStatus(): string { return $this->status; }
    public function getDescription(): string { return $this->description; }

    /* ---- setters (each one validates) ---- */

    public function setVehicleId(string $vehicleId): void
    {
        $vehicleId = trim($vehicleId);
        if ($vehicleId === '') {
            throw new ValidationException('Vehicle ID is required.');
        }
        $this->vehicleId = $vehicleId;
    }

    public function setBrand(string $brand): void
    {
        $brand = trim($brand);
        if ($brand === '') {
            throw new ValidationException('Brand is required.');
        }
        $this->brand = $brand;
    }

    public function setModel(string $model): void
    {
        $model = trim($model);
        if ($model === '') {
            throw new ValidationException('Model is required.');
        }
        $this->model = $model;
    }

    /** 0 means "unknown"; otherwise 1980-2100. */
    public function setYear(int $year): void
    {
        if ($year !== 0 && ($year < 1980 || $year > 2100)) {
            throw new ValidationException('Enter a valid year.');
        }
        $this->year = $year;
    }

    public function setPlateNumber(string $plateNumber): void
    {
        $plateNumber = strtoupper(trim($plateNumber));
        if ($plateNumber === '') {
            throw new ValidationException('Plate number is required.');
        }
        $this->plateNumber = $plateNumber;
    }

    public function setTransmission(string $transmission): void
    {
        if (!in_array($transmission, Config::TRANSMISSIONS, true)) {
            throw new ValidationException('Choose a valid transmission.');
        }
        $this->transmission = $transmission;
    }

    public function setFuelType(string $fuelType): void
    {
        if (!in_array($fuelType, Config::FUEL_TYPES, true)) {
            throw new ValidationException('Choose a valid fuel type.');
        }
        $this->fuelType = $fuelType;
    }

    public function setSeatingCapacity(int $seats): void
    {
        $this->seatingCapacity = max(1, $seats);
    }

    public function setDailyRate(float $dailyRate): void
    {
        if ($dailyRate <= 0) {
            throw new ValidationException('Enter a daily rate greater than zero.');
        }
        $this->dailyRate = $dailyRate;
    }

    public function setStatus(string $status): void
    {
        if (!in_array($status, Config::VEHICLE_STATUSES, true)) {
            throw new ValidationException('Choose a valid vehicle status.');
        }
        $this->status = $status;
    }

    public function setDescription(string $description): void
    {
        $this->description = trim($description);
    }
}
