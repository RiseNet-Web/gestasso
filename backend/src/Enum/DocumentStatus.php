<?php

namespace App\Enum;

enum DocumentStatus: string
{
    case PENDING = 'pending';
    case VALIDATED = 'validated';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::VALIDATED => 'Validé',
            self::REJECTED => 'Rejeté',
            self::EXPIRED => 'Expiré',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 