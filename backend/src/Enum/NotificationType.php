<?php

namespace App\Enum;

enum NotificationType: string
{
    case PAYMENT_DUE = 'payment_due';
    case PAYMENT_OVERDUE = 'payment_overdue';
    case DOCUMENT_MISSING = 'document_missing';
    case DOCUMENT_VALIDATED = 'document_validated';
    case DOCUMENT_REJECTED = 'document_rejected';
    case CAGNOTTE_UPDATED = 'cagnotte_updated';
    case EVENT_CREATED = 'event_created';
    case EVENT_DISTRIBUTED = 'event_distributed';

    public function getLabel(): string
    {
        return match($this) {
            self::PAYMENT_DUE => 'Paiement dû',
            self::PAYMENT_OVERDUE => 'Paiement en retard',
            self::DOCUMENT_MISSING => 'Document manquant',
            self::DOCUMENT_VALIDATED => 'Document validé',
            self::DOCUMENT_REJECTED => 'Document rejeté',
            self::CAGNOTTE_UPDATED => 'Cagnotte mise à jour',
            self::EVENT_CREATED => 'Événement créé',
            self::EVENT_DISTRIBUTED => 'Événement distribué',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public function isPaymentNotification(): bool
    {
        return in_array($this, [self::PAYMENT_DUE, self::PAYMENT_OVERDUE]);
    }

    public function isDocumentNotification(): bool
    {
        return in_array($this, [
            self::DOCUMENT_MISSING,
            self::DOCUMENT_VALIDATED,
            self::DOCUMENT_REJECTED
        ]);
    }

    public function isCagnotteNotification(): bool
    {
        return in_array($this, [
            self::CAGNOTTE_UPDATED,
            self::EVENT_CREATED,
            self::EVENT_DISTRIBUTED
        ]);
    }
} 