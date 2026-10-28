<?php
declare(strict_types=1);

/** Calendar-date helpers shared by the pricing calculator and the fleet service. */
trait DateHelpers
{
    protected function toDate(?string $value): ?DateTimeImmutable
    {
        if (!$value) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        // Reject overflow dates such as 2026-02-31, which createFromFormat would roll into March.
        return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $date : null;
    }

    /** Whole days from $from to $to (negative when $to is earlier). 0 if either date is invalid. */
    protected function daysBetween(?string $from, ?string $to): int
    {
        $start = $this->toDate($from);
        $end   = $this->toDate($to);
        if (!$start || !$end) {
            return 0;
        }
        return (int) $start->diff($end)->format('%r%a');
    }
}
