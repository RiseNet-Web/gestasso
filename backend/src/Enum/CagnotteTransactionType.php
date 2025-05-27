<?php

namespace App\Enum;

enum CagnotteTransactionType: string
{
    case EARNING = 'earning';
    case USAGE = 'usage';
    case ADJUSTMENT = 'adjustment';

    public function getLabel(): string
    {
        return match($this) {
            self::EARNING => 'Gain',
            self::USAGE => 'Utilisation',
            self::ADJUSTMENT => 'Ajustement',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 