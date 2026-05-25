<?php

namespace App\Repository;

use App\Document\Session;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use MongoDB\BSON\ObjectId;

/**
 * @extends DocumentRepository<Session>
 */
class SessionRepository extends DocumentRepository
{
    public function __construct(DocumentManager $dm)
    {
        $uow           = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(Session::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    /**
     * Returns a paginated, optionally filtered list of active sessions.
     *
     * @param  array{language?: string, location?: string} $filters
     * @return array{data: Session[], total: int}
     */
    public function findPaginated(int $page, int $limit, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder()
            ->field('active')->equals(true);

        if (!empty($filters['language'])) {
            // Case-insensitive match on the language field
            $qb->field('language')->equals(
                new \MongoDB\BSON\Regex('^' . preg_quote($filters['language'], '/') . '$', 'i')
            );
        }

        if (!empty($filters['location'])) {
            // Partial, case-insensitive search on the location field
            $qb->field('location')->equals(
                new \MongoDB\BSON\Regex(preg_quote($filters['location'], '/'), 'i')
            );
        }

        $countQb = clone $qb;

        $data = iterator_to_array(
            $qb->sort('date', 'asc')->skip($offset)->limit($limit)->getQuery()->execute()
        );

        $total = (int) $countQb->count()->getQuery()->execute();

        return ['data' => array_values($data), 'total' => $total];
    }

    /**
     * Atomically decrements availableSeats by 1 if seats remain.
     *
     * Uses MongoDB's findOneAndUpdate to check + decrement in a SINGLE atomic
     * operation — eliminating the read/check/write race condition.
     *
     * @return bool  true  → seat reserved successfully
     *               false → session not found or no seats left
     */
    public function decrementAvailableSeatsAtomic(string $sessionId): bool
    {
        try {
            $oid = new ObjectId($sessionId);
        } catch (\Exception) {
            return false;
        }

        $collection = $this->getDocumentManager()
            ->getDocumentCollection(Session::class);

        $result = $collection->findOneAndUpdate(
            // Filter: session exists AND still has seats
            ['_id' => $oid, 'availableSeats' => ['$gt' => 0]],
            // Atomic decrement
            ['$inc' => ['availableSeats' => -1]],
            ['returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );

        if ($result === null) {
            return false;
        }

        // Refresh the ODM-tracked entity so its in-memory availableSeats
        // matches the value we just wrote atomically.
        // Using refresh() instead of detach() keeps the session managed —
        // detach() would invalidate any reference to the same object held
        // by the caller (e.g. the Reservation we're about to persist).
        $tracked = $this->find($sessionId);
        if ($tracked !== null) {
            $this->getDocumentManager()->refresh($tracked);
        }

        return true;
    }

    /**
     * Atomically increments availableSeats by 1 (used on reservation cancel).
     *
     * Capped at totalSeats to prevent inconsistencies.
     */
    public function incrementAvailableSeatsAtomic(string $sessionId): void
    {
        try {
            $oid = new ObjectId($sessionId);
        } catch (\Exception) {
            return;
        }

        $collection = $this->getDocumentManager()
            ->getDocumentCollection(Session::class);

        // Only increment if availableSeats < totalSeats (safety guard)
        $collection->updateOne(
            ['_id' => $oid, '$expr' => ['$lt' => ['$availableSeats', '$totalSeats']]],
            ['$inc' => ['availableSeats' => 1]]
        );
    }
}
