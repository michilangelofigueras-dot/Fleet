<?php
declare(strict_types=1);

/** Anything that can turn itself into a record for data/records.json. */
interface ExportableInterface
{
    public function toArray(): array;
}
