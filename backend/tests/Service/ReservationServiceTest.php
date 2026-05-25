<?php

namespace App\Tests\Service;

use App\Document\Reservation;
use App\Document\Session;
use App\Exception\AppException;
use App\Repository\ReservationRepository;
use App\Repository\SessionRepository;
use App\Service\ReservationService;
use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit tests for ReservationService.
 *
 * All dependencies are mocked — no database, no HTTP.
 * Focuses on business rules: overbooking prevention, duplicate detection,
 * seat-rollback on flush error, cancellation logic.
 */
class ReservationServiceTest extends TestCase
{
    private DocumentManager&MockObject      $dm;
    private SessionRepository&MockObject    $sessionRepo;
    private ReservationRepository&MockObject $reservationRepo;
    private ReservationService              $service;

    protected function setUp(): void
    {
        $this->dm              = $this->createMock(DocumentManager::class);
        $this->sessionRepo     = $this->createMock(SessionRepository::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);

        $this->service = new ReservationService(
            $this->dm,
            $this->sessionRepo,
            $this->reservationRepo,
        );
    }

    // ── book() — guard checks ─────────────────────────────────────────────────

    public function test_book_throws_409_for_inactive_session(): void
    {
        $session = $this->makeSession(active: false);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->service->book($session, 'user-id');
    }

    public function test_book_throws_409_when_user_already_has_active_reservation(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo
            ->method('hasActiveReservation')
            ->willReturn(true);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->service->book($session, 'user-id');
    }

    public function test_book_throws_409_when_no_seats_available(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo->method('hasActiveReservation')->willReturn(false);
        $this->sessionRepo->method('decrementAvailableSeatsAtomic')->willReturn(false);

        $this->expectException(AppException::class);
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->service->book($session, 'user-id');
    }

    // ── book() — happy path ───────────────────────────────────────────────────

    public function test_book_persists_and_returns_active_reservation(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo->method('hasActiveReservation')->willReturn(false);
        $this->sessionRepo->method('decrementAvailableSeatsAtomic')->willReturn(true);

        $this->dm->expects($this->once())->method('persist');
        $this->dm->expects($this->once())->method('flush');

        $reservation = $this->service->book($session, 'user-id');

        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertTrue($reservation->isActive());
        $this->assertSame('user-id', $reservation->getUserId());
    }

    // ── book() — error recovery ───────────────────────────────────────────────

    public function test_book_rolls_back_seat_when_flush_fails(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo->method('hasActiveReservation')->willReturn(false);
        $this->sessionRepo->method('decrementAvailableSeatsAtomic')->willReturn(true);
        $this->dm->method('flush')->willThrowException(new \RuntimeException('connection lost'));

        // Seat must be restored even on unexpected error
        $this->sessionRepo
            ->expects($this->once())
            ->method('incrementAvailableSeatsAtomic');

        $this->expectException(AppException::class);
        $this->expectExceptionCode(Response::HTTP_INTERNAL_SERVER_ERROR);

        $this->service->book($session, 'user-id');
    }

    public function test_book_rolls_back_seat_and_returns_409_on_duplicate_key_error(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo->method('hasActiveReservation')->willReturn(false);
        $this->sessionRepo->method('decrementAvailableSeatsAtomic')->willReturn(true);

        // Simulate MongoDB E11000 from the partial unique index
        $this->dm->method('flush')
            ->willThrowException(new \RuntimeException('E11000 duplicate key error'));

        $this->sessionRepo
            ->expects($this->once())
            ->method('incrementAvailableSeatsAtomic');

        $this->expectException(AppException::class);
        $this->expectExceptionCode(Response::HTTP_CONFLICT);

        $this->service->book($session, 'user-id');
    }

    // ── cancel() ──────────────────────────────────────────────────────────────

    public function test_cancel_marks_reservation_inactive_and_sets_cancelled_at(): void
    {
        $session     = $this->makeSession();
        $reservation = new Reservation();
        $reservation->setSession($session)->setUserId('user-id');

        $this->dm->expects($this->once())->method('flush');
        $this->sessionRepo->expects($this->once())->method('incrementAvailableSeatsAtomic');

        $this->service->cancel($reservation);

        $this->assertFalse($reservation->isActive());
        $this->assertNotNull($reservation->getCancelledAt());
    }

    public function test_cancel_increments_seat_for_the_correct_session(): void
    {
        $session     = $this->makeSession();
        $reservation = new Reservation();
        $reservation->setSession($session)->setUserId('user-id');

        $this->sessionRepo
            ->expects($this->once())
            ->method('incrementAvailableSeatsAtomic')
            ->with($session->getId());

        $this->service->cancel($reservation);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeSession(bool $active = true): Session
    {
        $session = new Session();
        $session->setLanguage('English')
                ->setDate(new \DateTimeImmutable('+1 month'))
                ->setTime('09:00')
                ->setLocation('Test Center')
                ->setTotalSeats(10)
                ->setAvailableSeats(10)
                ->setActive($active);

        return $session;
    }
}
