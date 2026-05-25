<?php

namespace App\Tests\Service;

use App\Document\Reservation;
use App\Document\Session;
use App\DTO\SessionRequest;
use App\Repository\ReservationRepository;
use App\Service\SessionService;
use Doctrine\ODM\MongoDB\DocumentManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SessionService.
 *
 * Verifies seat arithmetic on create/update and cascade-cancel on delete.
 */
class SessionServiceTest extends TestCase
{
    private DocumentManager&MockObject       $dm;
    private ReservationRepository&MockObject $reservationRepo;
    private SessionService                   $service;

    protected function setUp(): void
    {
        $this->dm              = $this->createMock(DocumentManager::class);
        $this->reservationRepo = $this->createMock(ReservationRepository::class);

        $this->service = new SessionService($this->dm, $this->reservationRepo);
    }

    // ── create() ──────────────────────────────────────────────────────────────

    public function test_create_sets_available_seats_equal_to_total(): void
    {
        $this->dm->expects($this->once())->method('persist');
        $this->dm->expects($this->once())->method('flush');

        $session = $this->service->create($this->makeDto(totalSeats: 25));

        $this->assertSame(25, $session->getTotalSeats());
        $this->assertSame(25, $session->getAvailableSeats());
        $this->assertTrue($session->isActive());
    }

    public function test_create_maps_dto_fields_correctly(): void
    {
        $this->dm->method('persist');
        $this->dm->method('flush');

        $session = $this->service->create($this->makeDto(totalSeats: 10));

        $this->assertSame('English', $session->getLanguage());
        $this->assertSame('09:00', $session->getTime());
        $this->assertSame('Test Center', $session->getLocation());
    }

    // ── update() — seat arithmetic ────────────────────────────────────────────

    public function test_update_increases_available_seats_when_total_grows(): void
    {
        // 10 total, 7 available → 3 are booked
        $session = $this->makeSession(total: 10, available: 7);

        $this->dm->expects($this->once())->method('flush');

        $updated = $this->service->update($session, $this->makeDto(totalSeats: 15));

        $this->assertSame(15, $updated->getTotalSeats());
        $this->assertSame(12, $updated->getAvailableSeats()); // 7 + (15−10)
    }

    public function test_update_decreases_available_seats_when_total_shrinks(): void
    {
        // 10 total, 8 available → 2 are booked
        $session = $this->makeSession(total: 10, available: 8);

        $this->dm->expects($this->once())->method('flush');

        $updated = $this->service->update($session, $this->makeDto(totalSeats: 6));

        $this->assertSame(6, $updated->getTotalSeats());
        $this->assertSame(4, $updated->getAvailableSeats()); // 8 + (6−10)
    }

    public function test_update_clamps_available_seats_to_zero_when_total_drops_below_bookings(): void
    {
        // 10 total, 2 available → 8 are booked; new total 5 < 8 booked
        $session = $this->makeSession(total: 10, available: 2);

        $this->dm->expects($this->once())->method('flush');

        $updated = $this->service->update($session, $this->makeDto(totalSeats: 5));

        $this->assertSame(5, $updated->getTotalSeats());
        $this->assertSame(0, $updated->getAvailableSeats()); // max(0, 2+(5−10))
    }

    public function test_update_keeps_available_seats_unchanged_when_total_is_same(): void
    {
        $session = $this->makeSession(total: 10, available: 6);

        $this->dm->expects($this->once())->method('flush');

        $updated = $this->service->update($session, $this->makeDto(totalSeats: 10));

        $this->assertSame(10, $updated->getTotalSeats());
        $this->assertSame(6, $updated->getAvailableSeats()); // unchanged
    }

    // ── delete() ──────────────────────────────────────────────────────────────

    public function test_delete_marks_session_inactive(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo->method('findActiveBySessionId')->willReturn([]);
        $this->dm->expects($this->once())->method('flush');

        $this->service->delete($session);

        $this->assertFalse($session->isActive());
    }

    public function test_delete_cascade_cancels_all_active_reservations(): void
    {
        $session      = $this->makeSession();
        $reservation1 = new Reservation();
        $reservation2 = new Reservation();
        $reservation3 = new Reservation();

        $this->reservationRepo
            ->method('findActiveBySessionId')
            ->willReturn([$reservation1, $reservation2, $reservation3]);

        $this->dm->expects($this->once())->method('flush');

        $this->service->delete($session);

        foreach ([$reservation1, $reservation2, $reservation3] as $r) {
            $this->assertFalse($r->isActive(), 'Each reservation must be cancelled');
            $this->assertNotNull($r->getCancelledAt(), 'cancelledAt must be set');
        }
    }

    public function test_delete_persists_in_a_single_flush(): void
    {
        $session = $this->makeSession();

        $this->reservationRepo
            ->method('findActiveBySessionId')
            ->willReturn([new Reservation(), new Reservation()]);

        // Exactly one flush — not one per reservation
        $this->dm->expects($this->once())->method('flush');

        $this->service->delete($session);
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function makeDto(int $totalSeats = 10): SessionRequest
    {
        return new SessionRequest(
            language:   'English',
            date:       '2027-06-15',
            time:       '09:00',
            location:   'Test Center',
            totalSeats: $totalSeats,
        );
    }

    private function makeSession(int $total = 10, int $available = 10, bool $active = true): Session
    {
        $session = new Session();
        $session->setLanguage('English')
                ->setDate(new \DateTimeImmutable('+1 month'))
                ->setTime('09:00')
                ->setLocation('Test Center')
                ->setTotalSeats($total)
                ->setAvailableSeats($available)
                ->setActive($active);

        return $session;
    }
}
