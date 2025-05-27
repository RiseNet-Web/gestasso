<?php

namespace App\Enum;

enum AuthProvider: string
{
    case EMAIL = 'email';
    case GOOGLE = 'google';
    case APPLE = 'apple';

    public function getLabel(): string
    {
        return match($this) {
            self::EMAIL => 'Email',
            self::GOOGLE => 'Google',
            self::APPLE => 'Apple',
        };
    }

    public static function getChoices(): array
    {
        return [
            'Email' => self::EMAIL->value,
            'Google' => self::GOOGLE->value,
            'Apple' => self::APPLE->value,
        ];
    }

    public function isEmail(): bool
    {
        return $this === self::EMAIL;
    }

    public function isGoogle(): bool
    {
        return $this === self::GOOGLE;
    }

    public function isApple(): bool
    {
        return $this === self::APPLE;
    }

    public function isSocial(): bool
    {
        return $this === self::GOOGLE || $this === self::APPLE;
    }

    public static function getSocialProviders(): array
    {
        return [self::GOOGLE, self::APPLE];
    }
} 