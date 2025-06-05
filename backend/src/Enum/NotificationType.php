<?php

namespace App\Enum;

enum NotificationType: string
{
    case DOCUMENT_MISSING = 'document_missing';
    case DOCUMENT_VALIDATED = 'document_validated';
    case DOCUMENT_REJECTED = 'document_rejected';
    case JOIN_REQUEST_RECEIVED = 'join_request_received';
    case JOIN_REQUEST_ACCEPTED = 'join_request_accepted';
    case JOIN_REQUEST_REJECTED = 'join_request_rejected';
    case MANAGER_INVITATION = 'manager_invitation';

    public function getLabel(): string
    {
        return match($this) {
            self::DOCUMENT_MISSING => 'Document manquant',
            self::DOCUMENT_VALIDATED => 'Document validé',
            self::DOCUMENT_REJECTED => 'Document rejeté',
            self::JOIN_REQUEST_RECEIVED => 'Demande d\'adhésion reçue',
            self::JOIN_REQUEST_ACCEPTED => 'Demande d\'adhésion acceptée',
            self::JOIN_REQUEST_REJECTED => 'Demande d\'adhésion rejetée',
            self::MANAGER_INVITATION => 'Invitation de gestionnaire',
        };
    }

    public static function getChoices(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }

    public function isDocumentNotification(): bool
    {
        return in_array($this, [
            self::DOCUMENT_MISSING,
            self::DOCUMENT_VALIDATED,
            self::DOCUMENT_REJECTED
        ]);
    }

    public function isJoinRequestNotification(): bool
    {
        return in_array($this, [
            self::JOIN_REQUEST_RECEIVED,
            self::JOIN_REQUEST_ACCEPTED,
            self::JOIN_REQUEST_REJECTED
        ]);
    }

    public function isManagementNotification(): bool
    {
        return $this === self::MANAGER_INVITATION;
    }
} 