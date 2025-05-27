<?php

namespace App\Enum;

enum TeamMemberRole: string
{
    case ATHLETE = 'ROLE_ATHLETE';
    case COACH = 'ROLE_COACH';

    public function getLabel(): string
    {
        return match($this) {
            self::ATHLETE => 'Athlète',
            self::COACH => 'Coach',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 