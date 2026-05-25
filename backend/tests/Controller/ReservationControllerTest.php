<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class ReservationControllerTest extends ApiTestCase
{
    // ── GET /api/reservations/me ──────────────────────────────────────────────

    public function test_my_reservations_returns_401_when_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/reservations/me');

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_my_reservations_returns_only_own_active_bookings(): void
    {
        $userA   = $this->createUser('a@test.com');
        $userB   = $this->createUser('b@test.com');
        $session = $this->createSession();

        $this->createReservation($session, (string) $userA->getId());

        // UserB should see an empty list
        $this->jsonRequest('GET', '/api/reservations/me', [], $userB);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertCount(0, $this->responseData());
    }

    public function test_my_reservations_lists_active_bookings(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(5);

        $this->createReservation($session, (string) $user->getId());

        $this->jsonRequest('GET', '/api/reservations/me', [], $user);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertCount(1, $this->responseData());
    }

    // ── POST /api/reservations ────────────────────────────────────────────────

    public function test_book_returns_401_when_unauthenticated(): void
    {
        $session = $this->createSession();

        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()]);

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_book_returns_201_and_reserves_seat(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(5);

        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()], $user);

        $this->assertJsonStatus(Response::HTTP_CREATED);

        $data = $this->responseData();
        $this->assertArrayHasKey('id', $data);
        $this->assertTrue($data['active']);
    }

    public function test_book_decrements_available_seats(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(5);

        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()], $user);

        $this->assertJsonStatus(Response::HTTP_CREATED);

        $this->dm()->refresh($session);
        $this->assertSame(4, $session->getAvailableSeats());
    }

    public function test_book_returns_404_for_unknown_session(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => '000000000000000000000000'], $user);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_book_returns_422_on_missing_session_id(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/reservations', [], $user);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('sessionId', $this->responseData()['errors']);
    }

    public function test_book_returns_409_when_no_seats_left(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(1);

        // Fill the only seat with another user
        $this->createReservation($session, 'other-user-000000000000000');

        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()], $user);

        $this->assertJsonStatus(Response::HTTP_CONFLICT);
        $this->assertSame('No available seats for this session.', $this->responseData()['message']);
    }

    public function test_book_returns_409_on_duplicate_booking(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(5);

        // First booking (direct, bypasses HTTP)
        $this->createReservation($session, (string) $user->getId());

        // Second booking attempt via HTTP
        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()], $user);

        $this->assertJsonStatus(Response::HTTP_CONFLICT);
        $this->assertSame('You have already booked this session.', $this->responseData()['message']);
    }

    public function test_book_allows_rebooking_after_cancellation(): void
    {
        $user        = $this->createUser();
        $session     = $this->createSession(5);
        $reservation = $this->createReservation($session, (string) $user->getId());

        // Cancel the existing reservation
        $this->jsonRequest('DELETE', '/api/reservations/' . $reservation->getId(), [], $user);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Re-book the same session — must succeed (no active reservation now)
        $this->jsonRequest('POST', '/api/reservations', ['sessionId' => $session->getId()], $user);
        $this->assertJsonStatus(Response::HTTP_CREATED);
    }

    // ── DELETE /api/reservations/{id} ─────────────────────────────────────────

    public function test_cancel_returns_401_when_unauthenticated(): void
    {
        $user        = $this->createUser();
        $session     = $this->createSession();
        $reservation = $this->createReservation($session, (string) $user->getId());

        $this->jsonRequest('DELETE', '/api/reservations/' . $reservation->getId());

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_cancel_returns_204_and_restores_seat(): void
    {
        $user        = $this->createUser();
        $session     = $this->createSession(5);
        $reservation = $this->createReservation($session, (string) $user->getId());

        $this->jsonRequest('DELETE', '/api/reservations/' . $reservation->getId(), [], $user);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Seat must be restored
        $this->dm()->refresh($session);
        $this->assertSame(5, $session->getAvailableSeats());
    }

    public function test_cancel_marks_reservation_inactive(): void
    {
        $user        = $this->createUser();
        $session     = $this->createSession();
        $reservation = $this->createReservation($session, (string) $user->getId());

        $this->jsonRequest('DELETE', '/api/reservations/' . $reservation->getId(), [], $user);

        $this->dm()->refresh($reservation);
        $this->assertFalse($reservation->isActive());
        $this->assertNotNull($reservation->getCancelledAt());
    }

    public function test_cancel_cannot_cancel_another_users_reservation(): void
    {
        $userA       = $this->createUser('a@test.com');
        $userB       = $this->createUser('b@test.com');
        $session     = $this->createSession(5);
        $reservation = $this->createReservation($session, (string) $userA->getId());

        // UserB tries to cancel UserA's reservation
        $this->jsonRequest('DELETE', '/api/reservations/' . $reservation->getId(), [], $userB);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);

        // Reservation must still be active
        $this->dm()->refresh($reservation);
        $this->assertTrue($reservation->isActive());
    }

    public function test_cancel_returns_404_for_unknown_id(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('DELETE', '/api/reservations/000000000000000000000000', [], $user);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }
}
