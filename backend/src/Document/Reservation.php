<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

#[ODM\Document(collection: 'reservations')]
#[ODM\Index(keys: ['userId' => 'asc'])]
#[ODM\Index(keys: ['sessionId' => 'asc'])]
#[ODM\Index(keys: ['active' => 'asc'])]
/**
 * Partial unique index: one ACTIVE booking per user per session.
 *
 * Only documents where active=true are included in the index.
 * A cancelled reservation (active=false) is invisible to it,
 * so the same user can re-book the same session after cancellation.
 *
 * This is the concurrency guarantee (Layer 2):
 * two simultaneous requests that both pass the hasActiveReservation()
 * soft-check will race to INSERT — MongoDB's index rejects the second
 * one with E11000, which the service catches and converts to a 409.
 */
#[ODM\Index(
    keys: ['sessionId' => 'asc', 'userId' => 'asc'],
    unique: true,
    partialFilterExpression: ['active' => ['$eq' => true]],
    name: 'unique_active_reservation_per_user_session',
)]
class Reservation
{
    #[ODM\Id]
    #[Groups(['reservation:read'])]
    private ?string $id = null;

    #[ODM\ReferenceOne(targetDocument: Session::class, storeAs: 'id')]
    #[Groups(['reservation:read'])]
    private ?Session $session = null;

    /** Internal — fast lookup queries */
    #[ODM\Field(type: 'string')]
    private string $sessionId = '';

    /** Internal — authenticated user is implicit */
    #[ODM\Field(type: 'string')]
    private string $userId = '';

    /** false = soft-deleted (cancelled) */
    #[ODM\Field(type: 'bool')]
    #[Groups(['reservation:read'])]
    private bool $active = true;

    #[ODM\Field(type: 'date')]
    #[Groups(['reservation:read'])]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
    private \DateTimeInterface $reservedAt;

    #[ODM\Field(type: 'date', nullable: true)]
    #[Groups(['reservation:read'])]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
    private ?\DateTimeInterface $cancelledAt = null;

    public function __construct()
    {
        $this->reservedAt = new \DateTimeImmutable();
    }

    public function getId(): ?string { return $this->id; }

    public function getSession(): ?Session { return $this->session; }
    public function setSession(Session $session): static
    {
        $this->session   = $session;
        $this->sessionId = (string) $session->getId();
        return $this;
    }

    public function getSessionId(): string { return $this->sessionId; }

    public function getUserId(): string { return $this->userId; }
    public function setUserId(string $userId): static { $this->userId = $userId; return $this; }

    public function isActive(): bool { return $this->active; }
    public function cancel(): void
    {
        $this->active      = false;
        $this->cancelledAt = new \DateTimeImmutable();
    }

    public function getReservedAt(): \DateTimeInterface { return $this->reservedAt; }
    public function getCancelledAt(): ?\DateTimeInterface { return $this->cancelledAt; }
}
