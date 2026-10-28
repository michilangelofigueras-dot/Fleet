<?php
declare(strict_types=1);

/**
 * Parent of VehicleRepository, CustomerRepository and RentalRepository.
 * Each child only says which section of records.json it owns and how its ids look.
 */
abstract class Repository implements RepositoryInterface, Seedable
{
    /** @var FleetDataManager */
    protected $data;

    public function __construct(FleetDataManager $data)
    {
        $this->data = $data;
    }

    /** Key inside records.json, e.g. "vehicles". */
    abstract protected function section(): string;

    /** Field holding the record id, e.g. "vehicle_id". */
    abstract protected function idKey(): string;

    /** Prefix for generated ids, e.g. "V". */
    abstract protected function idPrefix(): string;

    protected function idWidth(): int
    {
        return 3;
    }

    public function all(): array
    {
        return $this->data->getSection($this->section());
    }

    public function find(string $id): ?array
    {
        foreach ($this->all() as $row) {
            if (isset($row[$this->idKey()]) && (string) $row[$this->idKey()] === $id) {
                return $row;
            }
        }
        return null;
    }

    public function exists(string $id): bool
    {
        return $this->find($id) !== null;
    }

    public function save(array $record): bool
    {
        $rows     = $this->all();
        $id       = (string) ($record[$this->idKey()] ?? '');
        $replaced = false;

        foreach ($rows as $index => $existing) {
            if ((string) ($existing[$this->idKey()] ?? '') === $id) {
                $rows[$index] = $record;
                $replaced     = true;
            }
        }
        if (!$replaced) {
            $rows[] = $record;
        }

        $this->data->setSection($this->section(), $rows);
        return $replaced;
    }

    public function delete(string $id): void
    {
        $remaining = array_filter($this->all(), function (array $row) use ($id): bool {
            return (string) ($row[$this->idKey()] ?? '') !== $id;
        });
        $this->data->setSection($this->section(), $remaining);
    }

    public function replaceAll(array $rows): void
    {
        $this->data->setSection($this->section(), $rows);
    }

    public function nextId(): string
    {
        $highest = 0;
        foreach ($this->all() as $row) {
            $number  = (int) preg_replace('/[^0-9]/', '', (string) ($row[$this->idKey()] ?? ''));
            $highest = max($highest, $number);
        }
        return $this->idPrefix() . '-' . str_pad((string) ($highest + 1), $this->idWidth(), '0', STR_PAD_LEFT);
    }

    public function resetToSeed(): void
    {
        $this->replaceAll(static::seed());
    }
}
