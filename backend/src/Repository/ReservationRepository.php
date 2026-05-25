<?php

namespace App\Repository;

use App\Document\Reservation;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

/**
 * @extends DocumentRepository<Reservation>
 */
class ReservationRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        $uow           = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(Reservation::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    /**
     * Returns only ACTIVE reservations for a user (cancelled ones excluded).
     * Session proxies are explicitly initialized so the serializer receives
     * fully-hydrated Session objects instead of unresolved proxy stubs.
     *
     * @return Reservation[]
     */
    public function findActiveByUserId(string $userId): array
    {
        $reservations = $this->findBy(['userId' => $userId, 'active' => true]);
        $this->initializeSessionProxies($reservations);

        return $reservations;
    }

    /**
     * Checks whether a user already has an ACTIVE reservation for a session.
     * Cancelled reservations are ignored → user can re-book after cancellation.
     */
    public function hasActiveReservation(string $sessionId, string $userId): bool
    {
        $count = $this->createQueryBuilder()
            ->field('sessionId')->equals($sessionId)
            ->field('userId')->equals($userId)
            ->field('active')->equals(true)
            ->count()
            ->getQuery()
            ->execute();

        return $count > 0;
    }

    /**
     * Finds an ACTIVE reservation by ID belonging to a specific user.
     * Used for ownership check on cancel.
     */
    public function findActiveByIdAndUser(string $id, string $userId): ?Reservation
    {
        return $this->findOneBy([
            'id'     => $id,
            'userId' => $userId,
            'active' => true,
        ]);
    }

    /**
     * Returns ALL reservations for a user (active + cancelled).
     * Session proxies are explicitly initialized before returning.
     *
     * @return Reservation[]
     */
    public function findAllByUserId(string $userId): array
    {
        $reservations = $this->findBy(['userId' => $userId], ['reservedAt' => 'DESC']);
        $this->initializeSessionProxies($reservations);

        return $reservations;
    }

    /**
     * Forces Doctrine ODM to load the referenced Session proxy for every
     * reservation. Without this, the Symfony serializer may emit just the raw
     * MongoDB ObjectId string instead of the full Session object.
     *
     * @param Reservation[] $reservations
     */
    private function initializeSessionProxies(array $reservations): void
    {
        $dm = $this->getDocumentManager();
        foreach ($reservations as $reservation) {
            $session = $reservation->getSession();
            if ($session !== null && !$dm->getUnitOfWork()->isInIdentityMap($session)) {
                $dm->initializeObject($session);
            }
        }
    }

    /**
     * Returns all ACTIVE reservations for a given session.
     * Used to cascade-cancel when a session is deactivated.
     *
     * @return Reservation[]
     */
    public function findActiveBySessionId(string $sessionId): array
    {
        return $this->findBy(['sessionId' => $sessionId, 'active' => true]);
    }
}
