<?php
declare(strict_types=1);

/** A collection that can be restored to its demo records. */
interface Seedable
{
    public static function seed(): array;

    public function resetToSeed(): void;
}
