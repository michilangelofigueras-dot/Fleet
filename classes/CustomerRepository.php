<?php
declare(strict_types=1);

final class CustomerRepository extends Repository
{
    protected function section(): string
    {
        return 'customers';
    }

    protected function idKey(): string
    {
        return 'customer_id';
    }

    protected function idPrefix(): string
    {
        return 'C';
    }

    public static function seed(): array
    {
        return SeedData::customers();
    }
}
