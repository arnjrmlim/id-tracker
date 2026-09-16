<?php

namespace App\Enums;

enum IdStatus: string
{
    case PENDING        = 'PENDING';
    case FOR_PROCESSING = 'FOR PROCESSING';
    case READY          = 'READY';
    case RELEASED       = 'RELEASED';
    case LOST           = 'LOST';
    case DAMAGED        = 'DAMAGED';
    case CANCELLED      = 'CANCELLED';

    /**
     * All status values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Human-readable label (same as value for this enum).
     */
    public function label(): string
    {
        return $this->value;
    }

    /**
     * Bootstrap badge color class for each status.
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING        => 'bg-secondary',
            self::FOR_PROCESSING => 'bg-warning text-dark',
            self::READY          => 'bg-info text-dark',
            self::RELEASED       => 'bg-success',
            self::LOST           => 'bg-danger',
            self::DAMAGED        => 'bg-orange text-dark',
            self::CANCELLED      => 'bg-dark',
        };
    }

    /**
     * Text color override for light badges.
     */
    public function textClass(): string
    {
        return match ($this) {
            self::FOR_PROCESSING, self::READY, self::DAMAGED => 'text-dark',
            default => '',
        };
    }

    /**
     * Attempt to create from string, returning null on failure.
     */
    public static function tryFromValue(string $value): ?self
    {
        return self::tryFrom(strtoupper(trim($value)));
    }

    /**
     * All cases as select options [value => label].
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value;
        }
        return $options;
    }
}
