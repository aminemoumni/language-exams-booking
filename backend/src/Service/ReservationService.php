<?php

namespace App\Service;

use App\Document\Reservation;
use App\Document\Session;
use App\Exception\AppException;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\Driver\Exception\BulkWriteException;
use Symfony\Component\HttpFoundation\Response;

class ReservationService
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly SessionRepository $sessionRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {}

    /**
     * Books a seat on a session for a user.
     *
     * Two-layer atomic protection:
     *   1. findOneAndUpdate: check + decrement in one MongoDB op
     *   2. Partial unique index (sessionId+userId, active=true) catches concurrent duplicates
     *
     * @throws AppException 409 on business rule violations
     * @throws AppException 500 on unexpected persistence errors
     */
    public function book(Session $session, string $userId): Reservation
    {
        if (!$session->isActive()) {
            throw new AppException('This session is no longer available.', Response::HTTP_CONFLICT);
        }

        if ($this->reservationRepository->hasActiveReservation((string) $session->getId(), $userId)) {
            throw new AppException('You have already booked this session.', Response::HTTP_CONFLICT);
        }

        // ── ATOMIC LAYER 1 ── check + decrement in a single MongoDB op
        if (!$this->sessionRepository->decrementAvailableSeatsAtomic((string) $session->getId())) {
            throw new AppException('No available seats for this session.', Response::HTTP_CONFLICT);
        }

        // ── ATOMIC LAYER 2 ── the partial unique index declared on Reservation
        //    (sessionId + userId, active=true) makes the INSERT itself atomic with
        //    the uniqueness check — no gap, no race. Two requests that both pass the
        //    hasActiveReservation() soft-check above will collide here: MongoDB
        //    rejects the second with E11000, caught below and returned as 409.
        $reservation = new Reservation();
        $reservation->setSession($session);
        $reservation->setUserId($userId);

        try {
            $this->dm->persist($reservation);
            $this->dm->flush();
        } catch (\Throwable $e) {
            $this->sessionRepository->incrementAvailableSeatsAtomic((string) $session->getId());

            if ($this->isDuplicateKeyError($e)) {
                throw new AppException('You have already booked this session.', Response::HTTP_CONFLICT);
            }

            throw new AppException('Could not complete the reservation.', Response::HTTP_INTERNAL_SERVER_ERROR, $e);
        }

        return $reservation;
    }

    /**
     * Cancels an active reservation and restores the seat.
     */
    public function cancel(Reservation $reservation): void
    {
        $sessionId = $reservation->getSessionId();

        $reservation->cancel();
        $this->dm->flush();

        $this->sessionRepository->incrementAvailableSeatsAtomic($sessionId);
    }

    private function isDuplicateKeyError(\Throwable $e): bool
    {
        if ($e instanceof BulkWriteException) {
            return $e->getCode() === 11000;
        }

        return str_contains($e->getMessage(), 'E11000')
            || str_contains($e->getMessage(), 'duplicate key');
    }
}
