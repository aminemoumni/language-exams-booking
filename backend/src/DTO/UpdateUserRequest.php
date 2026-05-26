<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for PUT /api/me
 */
final class UpdateUserRequest
{
    #[Assert\Length(min: 2, max: 100, minMessage: 'Name must be at least {{ limit }} characters.')]
    public readonly ?string $name;

    #[Assert\Email(message: 'Please provide a valid email address.')]
    public readonly ?string $email;

    public function __construct(?string $name = null, ?string $email = null)
    {
        $this->name  = $name;
        $this->email = $email;
    }
}
