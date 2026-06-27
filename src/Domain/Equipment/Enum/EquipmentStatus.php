<?php

namespace App\Domain\Equipment\Enum;

enum EquipmentStatus: string
{
    case InProgress = "In progress";
    case Completed = "Completed";

    /**
     * Front-facing key used by the template/JS (data-status, filters, toggle).
     */
    public function key(): string
    {
        return match ($this) {
            self::InProgress => "in_progress",
            self::Completed => "complete",
        };
    }

    public static function fromKey(string $key): self
    {
        return match ($key) {
            "in_progress" => self::InProgress,
            "complete" => self::Completed,
            default => throw new \ValueError(sprintf('Unknown equipment status key "%s".', $key)),
        };
    }
}
