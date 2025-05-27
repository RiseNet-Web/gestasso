<?php

namespace App\Enum;

enum ClubTransactionType: string
{
    case COMMISSION = 'commission';
    case EXPENSE = 'expense';
    case ADJUSTMENT = 'adjustment';

    public function getLabel(): string
    {
        return match($this) {
            self::COMMISSION => 'Commission',
            self::EXPENSE => 'Dépense',
            self::ADJUSTMENT => 'Ajustement',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 