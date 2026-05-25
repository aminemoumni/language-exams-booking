<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for POST /api/auth/register
 */
final class RegisterRequest
{
    #[Assert\NotBlank(message: 'Name is required.')]
    #[Assert\Length(min: 2, max: 100, minMessage: 'Name must be at least {{ limit }} characters.')]
    public readonly string $name;

    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(message: 'Please provide a valid email address.')]
    public readonly string $email;

    #[Assert\NotBlank(message: 'Password is required.')]
    #[Assert\Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters.')]
    public readonly string $password;

    public function __construct(string $name, string $email, string $password)
    {
        $this->name     = $name;
        $this->email    = $email;
        $this->password = $password;
    }
}
