<?php

namespace App\Enum;

enum DocumentType: string
{
    case LICENSE = 'license';
    case MEDICAL_CERTIFICATE = 'medical_certificate';
    case INSURANCE = 'insurance';
    case PHOTO = 'photo';
    case AUTHORIZATION = 'authorization';
    case MINOR_EXIT_AUTHORIZATION = 'minor_exit_authorization';
    case PASSPORT = 'passport';
    case ESTA = 'esta';
    case OTHER = 'other';

    public function getLabel(): string
    {
        return match($this) {
            self::LICENSE => 'Licence',
            self::MEDICAL_CERTIFICATE => 'Certificat médical',
            self::INSURANCE => 'Assurance',
            self::PHOTO => 'Photo d\'identité',
            self::AUTHORIZATION => 'Autorisation parentale',
            self::MINOR_EXIT_AUTHORIZATION => 'Autorisation de sortie de territoire pour mineur',
            self::PASSPORT => 'Passeport',
            self::ESTA => 'ESTA',
            self::OTHER => 'Autre',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
} 