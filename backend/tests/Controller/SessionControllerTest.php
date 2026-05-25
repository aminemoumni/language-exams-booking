<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class SessionControllerTest extends ApiTestCase
{
    private const VALID_PAYLOAD = [
        'language'   => 'English',
        'date'       => '2027-06-15',
        'time'       => '09:00',
        'location'   => 'Paris — Centre Test',
        'totalSeats' => 20,
    ];

    // ── GET /api/sessions ─────────────────────────────────────────────────────

    public function test_list_returns_401_when_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/sessions');

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_list_returns_paginated_structure(): void
    {
        $user = $this->createUser();
        $this->createSession();
        $this->createSession();

        $this->jsonRequest('GET', '/api/sessions', [], $user);

        $this->assertJsonStatus(Response::HTTP_OK);

        $data = $this->responseData();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('page', $data);
        $this->assertArrayHasKey('limit', $data);
        $this->assertArrayHasKey('totalPages', $data);
        $this->assertCount(2, $data['data']);
        $this->assertSame(2, $data['total']);
    }

    public function test_list_excludes_inactive_sessions(): void
    {
        $user = $this->createUser();
        $this->createSession(10, true);  // active
        $this->createSession(10, false); // inactive — must not appear

        $this->jsonRequest('GET', '/api/sessions', [], $user);

        $data = $this->responseData();
        $this->assertSame(1, $data['total']);
        $this->assertCount(1, $data['data']);
    }

    public function test_list_respects_pagination_params(): void
    {
        $user = $this->createUser();
        $this->createSession();
        $this->createSession();
        $this->createSession();

        $this->jsonRequest('GET', '/api/sessions?page=1&limit=2', [], $user);

        $data = $this->responseData();
        $this->assertCount(2, $data['data']);
        $this->assertSame(3, $data['total']);
        $this->assertSame(2, $data['totalPages']);
    }

    // ── GET /api/sessions/{id} ────────────────────────────────────────────────

    public function test_show_returns_401_when_unauthenticated(): void
    {
        $session = $this->createSession();

        $this->jsonRequest('GET', '/api/sessions/' . $session->getId());

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_show_returns_session(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession();

        $this->jsonRequest('GET', '/api/sessions/' . $session->getId(), [], $user);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertSame((string) $session->getId(), $this->responseData()['id']);
    }

    public function test_show_returns_404_for_unknown_id(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('GET', '/api/sessions/000000000000000000000000', [], $user);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_show_returns_404_for_inactive_session(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession(10, false);

        $this->jsonRequest('GET', '/api/sessions/' . $session->getId(), [], $user);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }

    // ── POST /api/sessions ────────────────────────────────────────────────────

    public function test_create_returns_401_when_unauthenticated(): void
    {
        $this->jsonRequest('POST', '/api/sessions', self::VALID_PAYLOAD);

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_create_returns_403_for_regular_user(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/sessions', self::VALID_PAYLOAD, $user);

        $this->assertJsonStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_create_returns_201_for_admin(): void
    {
        $admin = $this->createAdmin();

        $this->jsonRequest('POST', '/api/sessions', self::VALID_PAYLOAD, $admin);

        $this->assertJsonStatus(Response::HTTP_CREATED);

        $data = $this->responseData();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('English', $data['language']);
        $this->assertSame(20, $data['totalSeats']);
        $this->assertSame(20, $data['availableSeats']); // starts full
    }

    public function test_create_returns_422_on_missing_fields(): void
    {
        $admin = $this->createAdmin();

        $this->jsonRequest('POST', '/api/sessions', [], $admin);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $errors = $this->responseData()['errors'];
        $this->assertArrayHasKey('language', $errors);
        $this->assertArrayHasKey('date', $errors);
        $this->assertArrayHasKey('time', $errors);
        $this->assertArrayHasKey('location', $errors);
    }

    public function test_create_returns_422_on_invalid_time_format(): void
    {
        $admin   = $this->createAdmin();
        $payload = array_merge(self::VALID_PAYLOAD, ['time' => '9:00']);

        $this->jsonRequest('POST', '/api/sessions', $payload, $admin);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('time', $this->responseData()['errors']);
    }

    // ── PUT /api/sessions/{id} ────────────────────────────────────────────────

    public function test_update_returns_403_for_regular_user(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession();

        $this->jsonRequest('PUT', '/api/sessions/' . $session->getId(), self::VALID_PAYLOAD, $user);

        $this->assertJsonStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_update_returns_200_and_reflects_changes(): void
    {
        $admin   = $this->createAdmin();
        $session = $this->createSession(10);

        $payload = array_merge(self::VALID_PAYLOAD, ['language' => 'French', 'totalSeats' => 15]);

        $this->jsonRequest('PUT', '/api/sessions/' . $session->getId(), $payload, $admin);

        $this->assertJsonStatus(Response::HTTP_OK);

        $data = $this->responseData();
        $this->assertSame('French', $data['language']);
        $this->assertSame(15, $data['totalSeats']);
    }

    public function test_update_adjusts_available_seats_on_total_change(): void
    {
        $admin   = $this->createAdmin();
        $session = $this->createSession(10); // 10 available

        // Book 3 seats directly
        $this->createReservation($session, 'user-1-0000000000000000000');
        $this->createReservation($session, 'user-2-0000000000000000000');
        $this->createReservation($session, 'user-3-0000000000000000000');

        // Increase total from 10 → 15
        $payload = array_merge(self::VALID_PAYLOAD, ['totalSeats' => 15]);

        $this->jsonRequest('PUT', '/api/sessions/' . $session->getId(), $payload, $admin);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertSame(12, $this->responseData()['availableSeats']); // 7 + 5
    }

    public function test_update_returns_404_for_unknown_session(): void
    {
        $admin = $this->createAdmin();

        $this->jsonRequest('PUT', '/api/sessions/000000000000000000000000', self::VALID_PAYLOAD, $admin);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }

    // ── DELETE /api/sessions/{id} ─────────────────────────────────────────────

    public function test_delete_returns_403_for_regular_user(): void
    {
        $user    = $this->createUser();
        $session = $this->createSession();

        $this->jsonRequest('DELETE', '/api/sessions/' . $session->getId(), [], $user);

        $this->assertJsonStatus(Response::HTTP_FORBIDDEN);
    }

    public function test_delete_soft_deletes_session(): void
    {
        $admin   = $this->createAdmin();
        $session = $this->createSession();

        $this->jsonRequest('DELETE', '/api/sessions/' . $session->getId(), [], $admin);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // The session must no longer be accessible (authenticated request)
        $this->jsonRequest('GET', '/api/sessions/' . $session->getId(), [], $admin);
        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }

    public function test_delete_cascade_cancels_active_reservations(): void
    {
        $admin       = $this->createAdmin();
        $user        = $this->createUser();
        $session     = $this->createSession(5);
        $reservation = $this->createReservation($session, (string) $user->getId());

        $this->jsonRequest('DELETE', '/api/sessions/' . $session->getId(), [], $admin);
        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Refresh from DB — reservation must be cancelled
        $this->dm()->refresh($reservation);
        $this->assertFalse($reservation->isActive());
        $this->assertNotNull($reservation->getCancelledAt());
    }

    public function test_delete_returns_404_for_unknown_session(): void
    {
        $admin = $this->createAdmin();

        $this->jsonRequest('DELETE', '/api/sessions/000000000000000000000000', [], $admin);

        $this->assertJsonStatus(Response::HTTP_NOT_FOUND);
    }
}
