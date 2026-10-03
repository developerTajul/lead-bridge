<?php

declare(strict_types=1);

namespace Core\Lead\Domain\Enums;

enum LeadStatus: string
{
    case NEW = 'new';
    case CONTACTED = 'contacted';
    case QUALIFIED = 'qualified';
    case CONVERTED = 'converted';
    case LOST = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::CONTACTED => 'Contacted',
            self::QUALIFIED => 'Qualified',
            self::CONVERTED => 'Converted',
            self::LOST => 'Lost',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
