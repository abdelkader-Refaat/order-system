<?php

namespace App\Enums;

enum OrderStatus: int
{
    case Pending = 0;
    case Processed = 1;
    case Failed = 2;

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Processed => 'processed',
            self::Failed => 'failed',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match ($label) {
            'pending' => self::Pending,
            'processed' => self::Processed,
            'failed' => self::Failed,
            default => throw new \ValueError("Invalid OrderStatus label: {$label}"),
        };
    }

    public static function labels(): array
    {
        return array_map(fn (self $case) => $case->label(), self::cases());
    }
}
