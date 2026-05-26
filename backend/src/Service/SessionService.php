<?php

namespace App\Service;

use App\Document\Session;
use App\DTO\SessionRequest;
use App\Repository\ReservationRepository;
use Doctrine\ODM\MongoDB\DocumentManager;

class SessionService
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    public function create(SessionRequest $dto): Session
    {
        $session = $this->applyDto(new Session(), $dto);
        $session->setAvailableSeats($dto->totalSeats);

        $this->dm->persist($session);
        $this->dm->flush();

        return $session;
    }

    public function update(Session $session, SessionRequest $dto): Session
    {
        $seatDiff     = $dto->totalSeats - $session->getTotalSeats();
        $newAvailable = max(0, $session->getAvailableSeats() + $seatDiff);

        $this->applyDto($session, $dto);
        $session->setAvailableSeats($newAvailable);

        $this->dm->flush();

        return $session;
    }

    /**
     * Soft-delete a session and cascade-cancel all its active reservations.
     *
     * When a session is cancelled:
     *   1. All active reservations are soft-deleted (active=false, cancelledAt=now).
     *   2. The session itself is marked inactive.
     *   3. A single flush persists everything atomically.
     *
     * Seat restoration is intentionally skipped — the session is gone,
     * its availableSeats count is no longer meaningful.
     */
    public function delete(Session $session): void
    {
        $reservations = $this->reservationRepository->findActiveBySessionId((string) $session->getId());

        foreach ($reservations as $reservation) {
            $reservation->cancel();
        }

        $session->setActive(false);

        $this->dm->flush();
    }

    private function applyDto(Session $session, SessionRequest $dto): Session
    {
        return $session
            ->setLanguage($dto->language)
            ->setDate(new \DateTimeImmutable($dto->date))
            ->setTime($dto->time)
            ->setLocation($dto->location)
            ->setTotalSeats($dto->totalSeats);
    }
}
