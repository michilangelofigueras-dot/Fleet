<?php
declare(strict_types=1);

/**
 * Business rules: validation, rental pricing, double-booking checks and keeping
 * vehicle statuses in step with bookings. Every public method returns a message
 * for the client, or throws an ApiException.
 */
final class FleetService
{
    use DateHelpers;
    use ValidatesInput;

    /** @var VehicleRepository */
    private $vehicles;
    /** @var CustomerRepository */
    private $customers;
    /** @var RentalRepository */
    private $rentals;
    /** @var PricingCalculator */
    private $pricing;

    public function __construct(
        VehicleRepository $vehicles,
        CustomerRepository $customers,
        RentalRepository $rentals,
        PricingCalculator $pricing
    ) {
        $this->vehicles  = $vehicles;
        $this->customers = $customers;
        $this->rentals   = $rentals;
        $this->pricing   = $pricing;
    }

    /* ============================ reads ============================ */

    public function allData(): array
    {
        return [
            'vehicles'  => $this->vehicles->all(),
            'customers' => $this->customers->all(),
            'rentals'   => $this->rentals->all(),
        ];
    }

    public function collection(string $name): array
    {
        switch ($name) {
            case 'vehicles':
                return $this->vehicles->all();
            case 'customers':
                return $this->customers->all();
            case 'rentals':
                return $this->rentals->all();
        }
        throw new NotFoundException('Unknown resource: ' . $name);
    }

    public function resetDemoData(): array
    {
        $this->vehicles->resetToSeed();
        $this->customers->resetToSeed();
        $this->rentals->resetToSeed();
        return $this->allData();
    }

    /* ============================ vehicles ============================ */

    public function saveVehicle(array $input, ?string $id): string
    {
        $vehicleId = $id !== null && $id !== '' ? $id : trim($this->inputText($input, 'vehicle_id'));
        if ($vehicleId === '') {
            throw new ValidationException('Vehicle ID is required.');
        }

        $existing = $this->vehicles->find($vehicleId);
        if ($id === null && $existing !== null) {
            throw new ValidationException('Vehicle ' . $vehicleId . ' already exists.');
        }
        if ($id !== null && $existing === null) {
            throw new NotFoundException('Vehicle ' . $vehicleId . ' was not found.');
        }

        // The Vehicle child class validates every field through its setters.
        $data = $input;
        $data['vehicle_id'] = $vehicleId;
        unset($data['created_at'], $data['updated_at']);
        $vehicle = VehicleFactory::create($this->inputText($input, 'category', 'Car'), $data);

        if ($this->vehicles->plateTaken($vehicle->getPlateNumber(), $vehicleId)) {
            throw new ValidationException('That plate number is already registered.');
        }

        if ($existing !== null && isset($existing['created_at']) && is_string($existing['created_at'])) {
            $vehicle->setCreatedAt($existing['created_at']);
        }
        $vehicle->markUpdated();

        $replaced = $this->vehicles->save($vehicle->toArray());

        // Status follows live bookings (only Maintenance is set by hand).
        $this->syncVehicleStatuses();

        return 'Vehicle ' . $vehicleId . ($replaced ? ' updated.' : ' added.');
    }

    public function deleteVehicle(string $id): string
    {
        if (!$this->vehicles->exists($id)) {
            throw new NotFoundException('Vehicle ' . $id . ' was not found.');
        }

        $linked = $this->rentals->countForVehicle($id);
        if ($linked > 0) {
            throw new ValidationException('Remove the ' . $linked . ' rental(s) linked to ' . $id . ' first.');
        }

        $this->vehicles->delete($id);
        return 'Vehicle ' . $id . ' deleted.';
    }

    /* ============================ customers ============================ */

    public function saveCustomer(array $input, ?string $id): string
    {
        $customerId = $id !== null && $id !== '' ? $id : trim($this->inputText($input, 'customer_id'));
        if ($customerId === '') {
            throw new ValidationException('Customer ID is required.');
        }

        $exists = $this->customers->exists($customerId);
        if ($id === null && $exists) {
            throw new ValidationException('Customer ' . $customerId . ' already exists.');
        }
        if ($id !== null && !$exists) {
            throw new NotFoundException('Customer ' . $customerId . ' was not found.');
        }

        $email = trim($this->inputText($input, 'email'));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Enter a valid email address.');
        }

        $replaced = $this->customers->save([
            'customer_id'    => $customerId,
            'full_name'      => $this->requireText($input, 'full_name', 'Full name'),
            'phone'          => $this->requireText($input, 'phone', 'Phone'),
            'email'          => $email,
            'address'        => trim($this->inputText($input, 'address')),
            'license_number' => $this->requireText($input, 'license_number', 'Licence number'),
        ]);

        return 'Customer ' . $customerId . ($replaced ? ' updated.' : ' added.');
    }

    public function deleteCustomer(string $id): string
    {
        if (!$this->customers->exists($id)) {
            throw new NotFoundException('Customer ' . $id . ' was not found.');
        }

        $linked = $this->rentals->countForCustomer($id);
        if ($linked > 0) {
            throw new ValidationException('Remove the ' . $linked . ' rental(s) linked to ' . $id . ' first.');
        }

        $this->customers->delete($id);
        return 'Customer ' . $id . ' deleted.';
    }

    /* ============================ rentals ============================ */

    /** Create ($id null) or update a rental; charges are always calculated here. */
    public function saveRental(array $input, ?string $id): string
    {
        $existing = null;
        if ($id !== null && $id !== '') {
            $existing = $this->rentals->find($id);
            if ($existing === null) {
                throw new NotFoundException('Rental ' . $id . ' was not found.');
            }
        }

        $vehicleId  = $this->requireText($input, 'vehicle_id', 'Vehicle');
        $customerId = $this->requireText($input, 'customer_id', 'Customer');

        $vehicle = $this->vehicles->find($vehicleId);
        if ($vehicle === null) {
            throw new ValidationException('That vehicle no longer exists.');
        }
        if (!$this->customers->exists($customerId)) {
            throw new ValidationException('That customer no longer exists.');
        }

        $start = $this->requireText($input, 'start_date', 'Rental start date');
        $end   = $this->requireText($input, 'end_date', 'Expected return date');
        if (!$this->toDate($start) || !$this->toDate($end)) {
            throw new ValidationException('Enter valid rental dates (YYYY-MM-DD).');
        }
        if ($this->daysBetween($start, $end) < 0) {
            throw new ValidationException('The expected return date cannot be before the start date.');
        }

        $actual = trim($this->inputText($input, 'actual_return_date'));
        if ($actual !== '') {
            if (!$this->toDate($actual)) {
                throw new ValidationException('Enter a valid actual return date.');
            }
            if ($this->daysBetween($start, $actual) < 0) {
                throw new ValidationException('The actual return date cannot be before the rental start date.');
            }
        }

        $status = trim($this->inputText($input, 'rental_status'));
        if ($status === '') {
            $status = $this->rentalStatusFor($start);
        }
        if (!in_array($status, Config::RENTAL_STATUSES, true)) {
            throw new ValidationException('Choose a valid rental status.');
        }

        $rentalId = $existing !== null ? (string) $id : $this->rentals->nextId();

        if ($status !== 'Cancelled') {
            $clash = $this->findOverlappingRental($vehicleId, $start, $end, $existing !== null ? $rentalId : null);
            if ($clash !== null) {
                throw new ValidationException($vehicleId . ' is already booked for those dates (' . $clash['rental_id'] . ').');
            }
        }

        // Editing must not reprice a booking when the vehicle's rate changed since:
        // keep the rate it was booked at, unless the vehicle itself was changed.
        $rate = (float) $vehicle['daily_rate'];
        if ($existing !== null
            && (string) ($existing['vehicle_id'] ?? '') === $vehicleId
            && (float) ($existing['daily_rate'] ?? 0) > 0) {
            $rate = (float) $existing['daily_rate'];
        }

        $days    = $this->pricing->rentalDays($start, $end);
        $priced  = VehicleFactory::fromRecord($vehicle);
        $priced->setDailyRate($rate);
        $cost    = $priced->calculateRentalCost($days);
        $extra   = $actual !== '' ? $this->pricing->lateDays($end, $actual) : 0;
        $penalty = $this->pricing->lateFee($rate, $extra);
        $total   = round($cost['total'] + $penalty, 2);
        $paid    = round((float) ($input['amount_paid'] ?? 0), 2);

        if ($paid < 0) {
            throw new ValidationException('Amount paid cannot be negative.');
        }
        if ($paid > $total + 0.01) {
            throw new ValidationException('Amount paid cannot be more than the total charge of ' . number_format($total, 2) . '.');
        }

        $this->rentals->save([
            'rental_id'          => $rentalId,
            'customer_id'        => $customerId,
            'vehicle_id'         => $vehicleId,
            'start_date'         => $start,
            'end_date'           => $end,
            'actual_return_date' => $actual,
            'rental_days'        => $days,
            'daily_rate'         => $rate,
            'base_cost'          => $cost['total'],
            'late_days'          => $extra,
            'late_fee'           => $penalty,
            'total_cost'         => $total,
            'amount_paid'        => $paid,
            'payment_status'     => $this->pricing->paymentStatus($total, $paid),
            'rental_status'      => $status,
            'notes'              => trim($this->inputText($input, 'notes')),
        ]);
        $this->syncVehicleStatuses();

        return 'Rental ' . $rentalId . ($existing !== null ? ' updated.' : ' created.');
    }

    public function deleteRental(string $id): string
    {
        if (!$this->rentals->exists($id)) {
            throw new NotFoundException('Rental ' . $id . ' was not found.');
        }

        $this->rentals->delete($id);
        $this->syncVehicleStatuses();

        return 'Rental ' . $id . ' deleted.';
    }

    /** Close a live rental with today's date, adding any late fee. */
    public function recordReturn(string $id): string
    {
        $rental = $this->rentals->find($id);
        if ($rental === null) {
            throw new NotFoundException('Rental ' . $id . ' was not found.');
        }
        if (!in_array($rental['rental_status'] ?? '', Config::LIVE_RENTAL_STATUSES, true)) {
            throw new ValidationException('Rental ' . $id . ' is already ' . strtolower((string) ($rental['rental_status'] ?? 'closed')) . ' and cannot be returned again.');
        }

        $today   = date('Y-m-d');
        $vehicle = $this->vehicles->find((string) $rental['vehicle_id']);
        $rate    = (float) ($rental['daily_rate'] ?? ($vehicle['daily_rate'] ?? 0));
        $days    = $this->pricing->rentalDays((string) $rental['start_date'], (string) $rental['end_date']);
        if ($rate <= 0) {
            throw new ValidationException('Rental ' . $id . ' has no daily rate recorded, so the return cannot be priced.');
        }
        $priced  = $vehicle !== null ? VehicleFactory::fromRecord($vehicle) : VehicleFactory::forPricing('Car', $rate);
        $priced->setDailyRate($rate);
        $cost    = $priced->calculateRentalCost($days);
        $extra   = $this->pricing->lateDays((string) $rental['end_date'], $today);
        $penalty = $this->pricing->lateFee($rate, $extra);
        $total   = round($cost['total'] + $penalty, 2);
        $paid    = round((float) ($rental['amount_paid'] ?? 0), 2);

        $rental['actual_return_date'] = $today;
        $rental['rental_days']        = $days;
        $rental['base_cost']          = $cost['total'];
        $rental['late_days']          = $extra;
        $rental['late_fee']           = $penalty;
        $rental['total_cost']         = $total;
        $rental['payment_status']     = $this->pricing->paymentStatus($total, $paid);
        $rental['rental_status']      = 'Completed';

        $this->rentals->save($rental);
        $this->syncVehicleStatuses();

        return 'Return recorded for ' . $id . ($penalty > 0 ? ' with a ' . number_format($penalty, 2) . ' late fee.' : '.');
    }

    /* ============================ helpers for the console app ============================ */

    /** Next free id for vehicles, customers or rentals. */
    public function nextId(string $resource): string
    {
        return $this->repository($resource)->nextId();
    }

    /** One record, or NotFoundException. */
    public function findRecord(string $resource, string $id): array
    {
        $record = $this->repository($resource)->find($id);
        if ($record === null) {
            throw new NotFoundException(ucfirst(rtrim($resource, 's')) . ' ' . $id . ' was not found.');
        }
        return $record;
    }

    /** Price a rental of one saved vehicle for $days days. */
    public function quote(string $vehicleId, int $days): array
    {
        $vehicle = VehicleFactory::fromRecord($this->findRecord('vehicles', $vehicleId));
        return ['vehicle' => $vehicle, 'cost' => $vehicle->calculateRentalCost($days)];
    }

    /**
     * The same call on three different objects: every category priced at one rate.
     * Which discount applies is decided by the object's own class.
     */
    public function compareCategories(float $dailyRate, int $days): array
    {
        $rows = [];
        foreach (Config::VEHICLE_CATEGORIES as $category) {
            $vehicle = VehicleFactory::forPricing($category, $dailyRate);
            $rows[]  = ['vehicle' => $vehicle, 'cost' => $vehicle->calculateRentalCost($days)];
        }
        return $rows;
    }

    private function repository(string $resource): RepositoryInterface
    {
        switch ($resource) {
            case 'vehicles':
                return $this->vehicles;
            case 'customers':
                return $this->customers;
            case 'rentals':
                return $this->rentals;
        }
        throw new NotFoundException('Unknown resource: ' . $resource);
    }

    /* ============================ rules ============================ */

    private function rentalStatusFor(string $startDate): string
    {
        $start = $this->toDate($startDate);
        if (!$start) {
            return 'Reserved';
        }
        return $start->format('Y-m-d') <= date('Y-m-d') ? 'Active' : 'Reserved';
    }

    /** A Reserved/Active booking of the same vehicle whose dates overlap, or null. */
    private function findOverlappingRental(string $vehicleId, string $start, string $end, ?string $excludeId): ?array
    {
        $from = $this->toDate($start);
        $to   = $this->toDate($end);
        if (!$from || !$to) {
            return null;
        }

        foreach ($this->rentals->all() as $rental) {
            if ((string) ($rental['vehicle_id'] ?? '') !== $vehicleId) {
                continue;
            }
            if ($excludeId !== null && (string) ($rental['rental_id'] ?? '') === $excludeId) {
                continue;
            }
            if (!in_array($rental['rental_status'] ?? '', Config::LIVE_RENTAL_STATUSES, true)) {
                continue;
            }

            $existingFrom = $this->toDate($rental['start_date'] ?? null);
            $existingTo   = $this->toDate($rental['end_date'] ?? null);
            if (!$existingFrom || !$existingTo) {
                continue;
            }
            if ($existingFrom <= $to && $existingTo >= $from) {
                return $rental;
            }
        }

        return null;
    }

    /** Rented if it has an Active booking, Reserved if only Reserved ones, else Available. */
    private function syncVehicleStatuses(): void
    {
        $vehicles = $this->vehicles->all();
        $rentals  = $this->rentals->all();

        foreach ($vehicles as $index => $vehicle) {
            if (($vehicle['status'] ?? '') === 'Maintenance') {
                continue;
            }

            $hasActive = false;
            $hasLive   = false;
            foreach ($rentals as $rental) {
                if ((string) ($rental['vehicle_id'] ?? '') !== (string) ($vehicle['vehicle_id'] ?? '')) {
                    continue;
                }
                $rentalStatus = $rental['rental_status'] ?? '';
                if (!in_array($rentalStatus, Config::LIVE_RENTAL_STATUSES, true)) {
                    continue;
                }
                $hasLive = true;
                if ($rentalStatus === 'Active') {
                    $hasActive = true;
                }
            }

            $vehicles[$index]['status'] = $hasActive ? 'Rented' : ($hasLive ? 'Reserved' : 'Available');
        }

        $this->vehicles->replaceAll($vehicles);
    }
}
