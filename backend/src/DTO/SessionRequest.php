<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Payload for POST /api/sessions  and  PUT /api/sessions/{id}
 */
final class SessionRequest
{
    #[Assert\NotBlank(message: 'Language is required.')]
    #[Assert\Length(min: 2, max: 100)]
    public readonly string $language;

    #[Assert\NotBlank(message: 'Date is required.')]
    #[Assert\Date(message: 'Date must be a valid date (YYYY-MM-DD).')]
    public readonly string $date;

    #[Assert\NotBlank(message: 'Time is required.')]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Time must be in HH:MM format.')]
    public readonly string $time;

    #[Assert\NotBlank(message: 'Location is required.')]
    #[Assert\Length(min: 2, max: 255)]
    public readonly string $location;

    #[Assert\NotNull(message: 'Total seats is required.')]
    #[Assert\Positive(message: 'Total seats must be a positive integer.')]
    public readonly int $totalSeats;

    public function __construct(
        string $language,
        string $date,
        string $time,
        string $location,
        int $totalSeats,
    ) {
        $this->language   = $language;
        $this->date       = $date;
        $this->time       = $time;
        $this->location   = $location;
        $this->totalSeats = $totalSeats;
    }

    /**
     * Rejects:
     *  - dates strictly before today (midnight UTC)
     *  - today's date with a time that has already passed (UTC)
     *
     * Runs after @Assert\Date and @Assert\Regex have validated the formats,
     * so $this->date and $this->time are well-formed when this is called.
     */
    #[Assert\Callback]
    public function validateNotInPast(ExecutionContextInterface $context): void
    {
        if (empty($this->date)) {
            return; // already caught by NotBlank
        }

        $utc = new \DateTimeZone('UTC');

        $today = new \DateTimeImmutable('today', $utc);
        $sessionDay = \DateTimeImmutable::createFromFormat('Y-m-d', $this->date, $utc);

        if ($sessionDay === false) {
            return; // already caught by @Assert\Date
        }

        // 1. Past date
        if ($sessionDay < $today) {
            $context
                ->buildViolation('The session date cannot be in the past.')
                ->atPath('date')
                ->addViolation();

            return; // no point checking the time
        }

        // 2. Today's date — verify the time is still in the future
        if ($sessionDay == $today && !empty($this->time)) {
            $sessionDateTime = \DateTimeImmutable::createFromFormat(
                'Y-m-d H:i',
                $this->date . ' ' . $this->time,
                $utc
            );

            if ($sessionDateTime !== false && $sessionDateTime <= new \DateTimeImmutable('now', $utc)) {
                $context
                    ->buildViolation('The session time has already passed.')
                    ->atPath('time')
                    ->addViolation();
            }
        }
    }
}
