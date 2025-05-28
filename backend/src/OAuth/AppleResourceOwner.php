<?php

namespace App\OAuth;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class AppleResourceOwner implements ResourceOwnerInterface
{
    /**
     * @var array
     */
    protected $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function getId()
    {
        return $this->data['sub'] ?? null;
    }

    public function getEmail(): ?string
    {
        return $this->data['email'] ?? null;
    }

    public function isEmailVerified(): bool
    {
        return $this->data['email_verified'] ?? false;
    }

    public function isPrivateEmail(): bool
    {
        return $this->data['is_private_email'] ?? false;
    }

    public function getRealUserStatus(): ?int
    {
        return $this->data['real_user_status'] ?? null;
    }

    public function toArray(): array
    {
        return $this->data;
    }
} 