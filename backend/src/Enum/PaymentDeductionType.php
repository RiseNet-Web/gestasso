<?php

namespace App\Enum;

enum PaymentDeductionType: string
{
    case CAGNOTTE = 'cagnotte';
    case DISCOUNT = 'discount';
    case SCHOLARSHIP = 'scholarship';
    case FAMILY_DISCOUNT = 'family_discount';
    case EARLY_PAYMENT = 'early_payment';
    case LOYALTY = 'loyalty';

    public function getLabel(): string
    {
        return match($this) {
            self::CAGNOTTE => 'Cagnotte',
            self::DISCOUNT => 'Remise',
            self::SCHOLARSHIP => 'Bourse',
            self::FAMILY_DISCOUNT => 'Remise familiale',
            self::EARLY_PAYMENT => 'Paiement anticipé',
            self::LOYALTY => 'Fidélité',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 