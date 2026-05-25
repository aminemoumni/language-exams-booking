<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends ApiTestCase
{
    // ── GET /api/me ───────────────────────────────────────────────────────────

    public function test_me_returns_401_when_unauthenticated(): void
    {
        $this->jsonRequest('GET', '/api/me');

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createUser('john@test.com', 'password123', ['ROLE_USER'], 'John Test');

        $this->jsonRequest('GET', '/api/me', [], $user);

        $this->assertJsonStatus(Response::HTTP_OK);

        $data = $this->responseData();
        $this->assertSame('john@test.com', $data['email']);
        $this->assertSame('John Test', $data['name']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('password', $data); // never expose password hash
    }

    // ── PUT /api/me ───────────────────────────────────────────────────────────

    public function test_update_returns_401_when_unauthenticated(): void
    {
        $this->jsonRequest('PUT', '/api/me', ['name' => 'New Name']);

        $this->assertJsonStatus(Response::HTTP_UNAUTHORIZED);
    }

    public function test_update_changes_name(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('PUT', '/api/me', ['name' => 'Updated Name'], $user);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertSame('Updated Name', $this->responseData()['name']);
    }

    public function test_update_changes_email(): void
    {
        $user = $this->createUser('old@test.com');

        $this->jsonRequest('PUT', '/api/me', ['email' => 'new@test.com'], $user);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertSame('new@test.com', $this->responseData()['email']);
    }

    public function test_update_returns_409_on_duplicate_email(): void
    {
        $this->createUser('taken@test.com');
        $user = $this->createUser('original@test.com');

        $this->jsonRequest('PUT', '/api/me', ['email' => 'taken@test.com'], $user);

        $this->assertJsonStatus(Response::HTTP_CONFLICT);
        $this->assertSame('Email already in use.', $this->responseData()['message']);
    }

    public function test_update_allows_keeping_same_email(): void
    {
        $user = $this->createUser('same@test.com');

        $this->jsonRequest('PUT', '/api/me', ['email' => 'same@test.com', 'name' => 'New Name'], $user);

        $this->assertJsonStatus(Response::HTTP_OK);
        $this->assertSame('same@test.com', $this->responseData()['email']);
        $this->assertSame('New Name', $this->responseData()['name']);
    }

    public function test_update_returns_422_on_invalid_email(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('PUT', '/api/me', ['email' => 'not-an-email'], $user);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('email', $this->responseData()['errors']);
    }

    public function test_update_returns_422_on_short_name(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('PUT', '/api/me', ['name' => 'X'], $user);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('name', $this->responseData()['errors']);
    }
}
