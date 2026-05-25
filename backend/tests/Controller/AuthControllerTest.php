<?php

namespace App\Tests\Controller;

use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthControllerTest extends ApiTestCase
{
    // ── POST /api/auth/register ───────────────────────────────────────────────

    public function test_register_returns_201_with_user_data(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(Response::HTTP_CREATED);

        $data = $this->responseData();
        $this->assertArrayHasKey('id', $data);
        $this->assertSame('john@example.com', $data['email']);
        $this->assertSame('John Doe', $data['name']);
        $this->assertArrayNotHasKey('password', $data); // never expose password
    }

    public function test_register_returns_409_on_duplicate_email(): void
    {
        $this->createUser('john@example.com');

        $this->jsonRequest('POST', '/api/auth/register', [
            'name'     => 'Another John',
            'email'    => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(Response::HTTP_CONFLICT);
        $this->assertSame('Email already in use.', $this->responseData()['message']);
    }

    public function test_register_returns_422_on_empty_body(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', []);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $errors = $this->responseData()['errors'];
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_register_returns_422_on_invalid_email(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name'     => 'John',
            'email'    => 'not-an-email',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('email', $this->responseData()['errors']);
    }

    public function test_register_returns_422_on_short_password(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name'     => 'John',
            'email'    => 'john@example.com',
            'password' => 'short',
        ]);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('password', $this->responseData()['errors']);
    }

    public function test_register_returns_422_on_short_name(): void
    {
        $this->jsonRequest('POST', '/api/auth/register', [
            'name'     => 'J',
            'email'    => 'john@example.com',
            'password' => 'password123',
        ]);

        $this->assertJsonStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertArrayHasKey('name', $this->responseData()['errors']);
    }

    // ── POST /api/auth/login ──────────────────────────────────────────────────

    public function test_login_returns_token_on_valid_credentials(): void
    {
        $this->createUser('user@test.com', 'password123');

        $this->jsonRequest('POST', '/api/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertArrayHasKey('token', $this->responseData());
    }

    public function test_login_returns_401_on_wrong_password(): void
    {
        $this->createUser('user@test.com', 'correctpassword');

        $this->jsonRequest('POST', '/api/auth/login', [
            'email'    => 'user@test.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function test_login_returns_401_on_unknown_email(): void
    {
        $this->jsonRequest('POST', '/api/auth/login', [
            'email'    => 'nobody@test.com',
            'password' => 'password123',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
