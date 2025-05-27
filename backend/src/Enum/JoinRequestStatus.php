<?php

namespace App\Enum;

enum JoinRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::APPROVED => 'Approuvée',
            self::REJECTED => 'Rejetée',
        };
    }

    public static function getChoices(): array
    {
        return [
            'En attente' => self::PENDING->value,
            'Approuvée' => self::APPROVED->value,
            'Rejetée' => self::REJECTED->value,
        ];
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isApproved(): bool
    {
        return $this === self::APPROVED;
    }

    public function isRejected(): bool
    {
        return $this === self::REJECTED;
    }

    public function isReviewed(): bool
    {
        return $this !== self::PENDING;
    }
} 