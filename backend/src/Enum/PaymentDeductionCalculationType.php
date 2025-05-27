<?php

namespace App\Enum;

enum PaymentDeductionCalculationType: string
{
    case FIXED = 'fixed';
    case PERCENTAGE = 'percentage';

    public function getLabel(): string
    {
        return match($this) {
            self::FIXED => 'Montant fixe',
            self::PERCENTAGE => 'Pourcentage',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 