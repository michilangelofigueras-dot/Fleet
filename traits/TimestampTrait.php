<?php
declare(strict_types=1);

/** created_at / updated_at bookkeeping, reusable by any class that stores records. */
trait TimestampTrait
{
    private $createdAt = null;
    private $updatedAt = null;

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /** Stamp "now" as the update time (and the creation time if it was never set). */
    public function markUpdated(): void
    {
        $this->updatedAt = date('Y-m-d H:i:s');
        if ($this->createdAt === null) {
            $this->createdAt = $this->updatedAt;
        }
    }
}
