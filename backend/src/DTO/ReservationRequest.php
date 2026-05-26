<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Payload for POST /api/reservations
 */
final class ReservationRequest
{
    #[Assert\NotBlank(message: 'Session ID is required.')]
    public readonly string $sessionId;

    public function __construct(string $sessionId)
    {
        $this->sessionId = $sessionId;
    }
}
