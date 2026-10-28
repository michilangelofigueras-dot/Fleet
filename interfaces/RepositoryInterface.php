<?php
declare(strict_types=1);

/** What every collection (vehicles, customers, rentals) can do. */
interface RepositoryInterface
{
    /** Every record in the collection. */
    public function all(): array;

    /** One record by id, or null. */
    public function find(string $id): ?array;

    public function exists(string $id): bool;

    /** Insert or replace by id. Returns true when an existing record was replaced. */
    public function save(array $record): bool;

    public function delete(string $id): void;

    /** Overwrite the whole collection. */
    public function replaceAll(array $rows): void;

    /** Next free id such as V-012 or R-0033. */
    public function nextId(): string;
}
