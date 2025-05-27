<?php

namespace App\Enum;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PARTIAL = 'partial';
    case PAID = 'paid';
    case OVERDUE = 'overdue';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::PARTIAL => 'Partiel',
            self::PAID => 'Payé',
            self::OVERDUE => 'En retard',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 