<?php

namespace App\Tests;

use App\Document\Reservation;
use App\Document\Session;
use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Base class for all API functional tests.
 *
 * Provides:
 *   - Database helpers: createUser(), createAdmin(), createSession(), createReservation()
 *   - HTTP helpers:     jsonRequest(), responseData(), assertJsonStatus()
 *   - Auth helper:      getToken() via JWTTokenManagerInterface (no login endpoint needed)
 *
 * Each test starts with a clean database — clearDatabase() wipes all three
 * collections and resets the ODM identity map.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->clearDatabase();
    }

    // ── Database helpers ──────────────────────────────────────────────────────

    protected function dm(): DocumentManager
    {
        /** @var DocumentManager $dm */
        $dm = static::getContainer()->get(DocumentManager::class);
        return $dm;
    }

    protected function clearDatabase(): void
    {
        $dm = $this->dm();
        $dm->getDocumentCollection(User::class)->deleteMany([]);
        $dm->getDocumentCollection(Session::class)->deleteMany([]);
        $dm->getDocumentCollection(Reservation::class)->deleteMany([]);
        $dm->clear();
    }

    protected function createUser(
        string $email    = 'user@test.com',
        string $password = 'password123',
        array  $roles    = ['ROLE_USER'],
        string $name     = 'Test User',
    ): User {
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $dm     = $this->dm();

        $user = new User();
        $user->setName($name)
             ->setEmail($email)
             ->setRoles($roles)
             ->setPassword($hasher->hashPassword($user, $password));

        $dm->persist($user);
        $dm->flush();

        return $user;
    }

    protected function createAdmin(
        string $email    = 'admin@test.com',
        string $password = 'password123',
    ): User {
        return $this->createUser($email, $password, ['ROLE_ADMIN', 'ROLE_USER'], 'Admin User');
    }

    protected function createSession(int $totalSeats = 10, bool $active = true): Session
    {
        $dm      = $this->dm();
        $session = new Session();

        $session->setLanguage('English')
                ->setDate(new \DateTimeImmutable('+1 month'))
                ->setTime('09:00')
                ->setLocation('Paris — Centre Test')
                ->setTotalSeats($totalSeats)
                ->setAvailableSeats($totalSeats)
                ->setActive($active);

        $dm->persist($session);
        $dm->flush();

        return $session;
    }

    /**
     * Creates a reservation directly in the DB (bypasses service layer).
     * Also decrements the session's availableSeats in-memory + flushes.
     */
    protected function createReservation(Session $session, string $userId): Reservation
    {
        $dm          = $this->dm();
        $reservation = new Reservation();
        $reservation->setSession($session)->setUserId($userId);
        $session->decrementAvailableSeats();

        $dm->persist($reservation);
        $dm->flush();

        return $reservation;
    }

    // ── HTTP helpers ──────────────────────────────────────────────────────────

    /**
     * Generates a JWT token for the given user via the bundle's own manager
     * (same path Symfony uses at runtime → zero risk of passphrase mismatch).
     */
    protected function getToken(User $user): string
    {
        /** @var JWTTokenManagerInterface $manager */
        $manager = static::getContainer()->get(JWTTokenManagerInterface::class);
        return $manager->create($user);
    }

    /**
     * Fires a JSON request, optionally authenticated as $asUser.
     */
    protected function jsonRequest(
        string $method,
        string $uri,
        array  $data   = [],
        ?User  $asUser = null,
    ): void {
        $headers = ['CONTENT_TYPE' => 'application/json'];

        if ($asUser !== null) {
            $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->getToken($asUser);
        }

        $this->client->request(
            $method,
            $uri,
            [],
            [],
            $headers,
            $data !== [] ? (string) json_encode($data) : '',
        );
    }

    /**
     * Decodes the last response body as JSON.
     */
    protected function responseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * Asserts HTTP status code and that the response is JSON.
     */
    protected function assertJsonStatus(int $status): void
    {
        $this->assertResponseStatusCodeSame($status);
        $this->assertJson($this->client->getResponse()->getContent());
    }
}
