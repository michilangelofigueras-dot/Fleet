<?php
declare(strict_types=1);

/**
 * The menu-driven console version of FleetFlow. Run it with:  php index.php
 *
 * Flow: load existing records -> show menu -> do an operation -> (save) -> exit.
 * Changes stay in memory until menu option 7 (or "yes" when exiting) writes them to
 * data/records.json; option 8 re-reads the file and discards unsaved changes.
 */
final class ConsoleApp
{
    private const TYPES = ['1' => 'vehicles', '2' => 'customers', '3' => 'rentals'];

    /** @var FleetService */
    private $fleet;
    /** @var FleetDataManager */
    private $data;
    private $input;
    private $endOfInput = false;

    /** @param resource|null $input where answers are read from (defaults to the keyboard) */
    public function __construct(FleetService $fleet, FleetDataManager $data, $input = null)
    {
        $this->fleet = $fleet;
        $this->data  = $data;
        $this->input = $input ?? STDIN;
    }

    public function run(): void
    {
        echo "Loading existing records...\n";
        try {
            $this->data->load();
            echo "Loaded " . count($this->fleet->collection('vehicles')) . " vehicle(s), "
                . count($this->fleet->collection('customers')) . " customer(s), "
                . count($this->fleet->collection('rentals')) . " rental(s).\n";
        } catch (ApiException $error) {
            echo "Error: " . $error->getMessage() . "\n";
            return;
        }

        $keepGoing = true;
        while ($keepGoing && !$this->endOfInput) {
            $this->printMenu();
            $choice = $this->ask('Enter your choice');
            if ($this->endOfInput) {
                break;
            }

            try {
                $keepGoing = $this->dispatch($choice);
            } catch (ApiException $error) {
                echo "Error: " . $error->getMessage() . "\n";
            } catch (Exception $error) {
                echo "Unexpected error: " . $error->getMessage() . "\n";
            } finally {
                echo "Operation completed.\n";
            }
        }

        if ($this->endOfInput && $this->data->isDirty()) {
            echo "\nInput ended with unsaved changes; they were not written to the file.\n";
        }
    }

    /* ============================ menu ============================ */

    private function printMenu(): void
    {
        echo "\n====================================\n";
        echo "   FLEETFLOW - VEHICLE RENTAL SYSTEM\n";
        echo "====================================\n\n";
        echo "1. Add Record\n";
        echo "2. View All Records\n";
        echo "3. Search Record\n";
        echo "4. Update Record\n";
        echo "5. Delete Record\n";
        echo "6. Process / Calculate\n";
        echo "7. Save Records\n";
        echo "8. Load Records\n";
        echo "9. Exit\n\n";
        if ($this->data->isDirty()) {
            echo "(unsaved changes)\n";
        }
    }

    /** @return bool false when the user chose Exit */
    private function dispatch(string $choice): bool
    {
        switch ($choice) {
            case '1':
                $this->addRecord();
                return true;
            case '2':
                $this->viewRecords();
                return true;
            case '3':
                $this->searchRecords();
                return true;
            case '4':
                $this->updateRecord();
                return true;
            case '5':
                $this->deleteRecord();
                return true;
            case '6':
                $this->process();
                return true;
            case '7':
                $this->data->save();
                echo "Records saved to data/records.json.\n";
                return true;
            case '8':
                $this->data->load();
                echo "Records loaded from data/records.json (unsaved changes discarded).\n";
                return true;
            case '9':
                return !$this->exitProgram();
        }
        throw new ValidationException('Invalid choice "' . $choice . '". Enter a number from 1 to 9.');
    }

    /** @return bool true when the program should end */
    private function exitProgram(): bool
    {
        if ($this->data->isDirty()) {
            $answer = strtolower($this->ask('You have unsaved changes. Save before exiting? (y/n)', 'y'));
            if ($answer === 'y' || $answer === 'yes') {
                $this->data->save();
                echo "Records saved to data/records.json.\n";
            }
        }
        echo "Thank you for using FleetFlow.\n";
        return true;
    }

    /* ============================ operations ============================ */

    private function addRecord(): void
    {
        $type  = $this->chooseType();
        $input = $this->collect($type, null);
        $this->say($this->save($type, $input, null));
    }

    private function viewRecords(): void
    {
        $type = $this->chooseType();
        $rows = $this->fleet->collection($type);
        echo "\n--- " . ucfirst($type) . ' (' . count($rows) . ") ---\n";
        if (!$rows) {
            echo "No records yet.\n";
        }
        foreach ($rows as $row) {
            echo $this->formatRecord($type, $row) . "\n";
        }
    }

    private function searchRecords(): void
    {
        $type    = $this->chooseType();
        $keyword = trim($this->ask('Search for'));
        if ($keyword === '') {
            throw new ValidationException('Enter something to search for.');
        }

        $found = 0;
        foreach ($this->fleet->collection($type) as $row) {
            foreach ($row as $value) {
                if (is_scalar($value) && stripos((string) $value, $keyword) !== false) {
                    echo $this->formatRecord($type, $row) . "\n";
                    $found++;
                    break;
                }
            }
        }
        echo $found . " match(es) for \"" . $keyword . "\".\n";
    }

    private function updateRecord(): void
    {
        $type     = $this->chooseType();
        $id       = trim($this->ask('ID of the record to update'));
        $existing = $this->fleet->findRecord($type, $id);

        echo "Press Enter to keep the current value, or type - to clear it.\n";
        $input = $this->collect($type, $existing);
        $this->say($this->save($type, $input, $id));
    }

    private function deleteRecord(): void
    {
        $type = $this->chooseType();
        $id   = trim($this->ask('ID of the record to delete'));
        $this->fleet->findRecord($type, $id);

        $answer = strtolower($this->ask('Delete ' . $id . '? (y/n)', 'n'));
        if ($answer !== 'y' && $answer !== 'yes') {
            echo "Nothing deleted.\n";
            return;
        }

        switch ($type) {
            case 'vehicles':
                $this->say($this->fleet->deleteVehicle($id));
                break;
            case 'customers':
                $this->say($this->fleet->deleteCustomer($id));
                break;
            default:
                $this->say($this->fleet->deleteRental($id));
        }
    }

    private function process(): void
    {
        echo "\n6.1 Rental quote for a vehicle\n";
        echo "6.2 Record a vehicle return (adds any late fee)\n";
        echo "6.3 Compare how each vehicle type prices a rental\n";
        $choice = $this->ask('Choose 1, 2 or 3');

        switch ($choice) {
            case '1':
                $vehicleId = trim($this->ask('Vehicle ID'));
                $days      = $this->positiveInt($this->ask('Number of days', '1'), 'Number of days');
                $quote     = $this->fleet->quote($vehicleId, $days);
                echo "\n" . $quote['vehicle']->describe() . "\n";
                $this->printCost($quote['cost']);
                break;

            case '2':
                $this->say($this->fleet->recordReturn(trim($this->ask('Rental ID'))));
                break;

            case '3':
                $rate = (float) $this->ask('Daily rate', '1500');
                $days = $this->positiveInt($this->ask('Number of days', '7'), 'Number of days');
                echo "\nThe same calculateRentalCost() call on three different objects:\n";
                foreach ($this->fleet->compareCategories($rate, $days) as $row) {
                    echo "\n" . str_pad($row['vehicle']->getCategory(), 11) . ' ';
                    echo 'total ' . $this->money($row['cost']['total']) . '  (discount ' . $this->money($row['cost']['discount']) . ")\n";
                }
                break;

            default:
                throw new ValidationException('Invalid choice "' . $choice . '". Enter 1, 2 or 3.');
        }
    }

    /* ============================ forms ============================ */

    /** Which fields each record type asks for: [key, label, default when adding, mode a/u/b]. */
    private function fieldsFor(string $type): array
    {
        switch ($type) {
            case 'vehicles':
                return [
                    ['category', 'Category (Car/Motorcycle/Van)', 'Car', 'b'],
                    ['brand', 'Brand', '', 'b'],
                    ['model', 'Model', '', 'b'],
                    ['year', 'Year', '', 'b'],
                    ['plate_number', 'Plate number', '', 'b'],
                    ['transmission', 'Transmission (Automatic/Manual)', 'Automatic', 'b'],
                    ['fuel_type', 'Fuel (Gasoline/Diesel/Electric/Hybrid)', 'Gasoline', 'b'],
                    ['seating_capacity', 'Seats (Enter for the category default)', '', 'b'],
                    ['daily_rate', 'Daily rate', '', 'b'],
                    ['status', 'Status (Available/Maintenance)', 'Available', 'b'],
                    ['description', 'Description', '', 'b'],
                ];
            case 'customers':
                return [
                    ['full_name', 'Full name', '', 'b'],
                    ['phone', 'Phone', '', 'b'],
                    ['email', 'Email', '', 'b'],
                    ['address', 'Address', '', 'b'],
                    ['license_number', 'Licence number', '', 'b'],
                ];
            default:
                return [
                    ['customer_id', 'Customer ID', '', 'b'],
                    ['vehicle_id', 'Vehicle ID', '', 'b'],
                    ['start_date', 'Start date (YYYY-MM-DD)', date('Y-m-d'), 'b'],
                    ['end_date', 'Expected return date (YYYY-MM-DD)', '', 'b'],
                    ['actual_return_date', 'Actual return date (YYYY-MM-DD)', '', 'u'],
                    ['rental_status', 'Status (Reserved/Active/Completed/Cancelled)', '', 'u'],
                    ['amount_paid', 'Amount paid', '0', 'b'],
                    ['notes', 'Notes', '', 'b'],
                ];
        }
    }

    /** Ask for every field. $existing is null when adding, or the saved record when updating. */
    private function collect(string $type, ?array $existing): array
    {
        $input = [];

        $idKey = $type === 'vehicles' ? 'vehicle_id' : ($type === 'customers' ? 'customer_id' : null);
        if ($existing === null && $idKey !== null) {
            $input[$idKey] = $this->ask(ucfirst(rtrim($type, 's')) . ' ID', $this->fleet->nextId($type));
        }

        foreach ($this->fieldsFor($type) as $field) {
            list($key, $label, $default, $mode) = $field;
            if ($existing === null && $mode === 'u') {
                continue;
            }
            $current = $existing !== null ? $this->scalarText($existing[$key] ?? '') : $default;
            $value   = $this->ask($label, $current);
            if ($existing !== null || $value !== '') {
                $input[$key] = $value;
            }
        }
        return $input;
    }

    private function save(string $type, array $input, ?string $id): string
    {
        switch ($type) {
            case 'vehicles':
                return $this->fleet->saveVehicle($input, $id);
            case 'customers':
                return $this->fleet->saveCustomer($input, $id);
        }
        return $this->fleet->saveRental($input, $id);
    }

    private function chooseType(): string
    {
        echo "\nRecord type: 1) Vehicle  2) Customer  3) Rental\n";
        $answer = trim($this->ask('Choose 1, 2 or 3'));
        if (!isset(self::TYPES[$answer])) {
            throw new ValidationException('Invalid record type "' . $answer . '". Enter 1, 2 or 3.');
        }
        return self::TYPES[$answer];
    }

    /* ============================ output helpers ============================ */

    private function formatRecord(string $type, array $row): string
    {
        switch ($type) {
            case 'vehicles':
                try {
                    // describe() is overridden by Car, Motorcycle and Van: runtime polymorphism.
                    return VehicleFactory::fromRecord($row)->describe() . '  [' . ($row['status'] ?? '?') . ']';
                } catch (ValidationException $error) {
                    return ($row['vehicle_id'] ?? '?') . '  (invalid record: ' . $error->getMessage() . ')';
                }
            case 'customers':
                return sprintf('%s  %s  %s  %s  Licence %s',
                    $row['customer_id'] ?? '?', $row['full_name'] ?? '', $row['phone'] ?? '', $row['email'] ?? '', $row['license_number'] ?? '');
            default:
                return sprintf('%s  %s <- %s  %s to %s  total %s, paid %s  [%s / %s]',
                    $row['rental_id'] ?? '?', $row['vehicle_id'] ?? '?', $row['customer_id'] ?? '?',
                    $row['start_date'] ?? '?', $row['end_date'] ?? '?',
                    $this->money((float) ($row['total_cost'] ?? 0)), $this->money((float) ($row['amount_paid'] ?? 0)),
                    $row['rental_status'] ?? '?', $row['payment_status'] ?? '?');
        }
    }

    private function printCost(array $cost): void
    {
        echo "  Days:     " . $cost['days'] . "\n";
        echo "  Subtotal: " . $this->money($cost['subtotal']) . "\n";
        echo "  Discount: " . $this->money($cost['discount']) . "\n";
        echo "  Total:    " . $this->money($cost['total']) . "\n";
    }

    private function money(float $amount): string
    {
        return 'PHP ' . number_format($amount, 2);
    }

    private function say(string $message): void
    {
        echo $message . "\n";
    }

    private function scalarText($value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function positiveInt(string $text, string $label): int
    {
        if (!ctype_digit(trim($text)) || (int) $text < 1) {
            throw new ValidationException($label . ' must be a whole number of 1 or more.');
        }
        return (int) $text;
    }

    /** Prompt and read one line. Enter keeps $default; "-" gives an empty answer. */
    private function ask(string $label, string $default = ''): string
    {
        echo $label . ($default !== '' ? ' [' . $default . ']' : '') . ': ';
        $line = fgets($this->input);
        if ($line === false) {
            $this->endOfInput = true;
            return $default;
        }

        $line = trim($line);
        if ($line === '-') {
            return '';
        }
        return $line === '' ? $default : $line;
    }
}
