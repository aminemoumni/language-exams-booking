<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'sessions')]
#[ODM\Index(keys: ['date' => 'asc'])]
#[ODM\Index(keys: ['language' => 'asc'])]
#[ODM\Index(keys: ['active' => 'asc'])]
class Session
{
    #[ODM\Id]
    #[Groups(['session:read'])]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: 'Language is required.')]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['session:read', 'session:write'])]
    private string $language = '';

    #[ODM\Field(type: 'date')]
    #[Assert\NotNull(message: 'Date is required.')]
    #[Groups(['session:read', 'session:write'])]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    private ?\DateTimeInterface $date = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: 'Time is required.')]
    #[Assert\Regex(pattern: '/^\d{2}:\d{2}$/', message: 'Time must be in HH:MM format.')]
    #[Groups(['session:read', 'session:write'])]
    private string $time = '';

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank(message: 'Location is required.')]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['session:read', 'session:write'])]
    private string $location = '';

    #[ODM\Field(type: 'int')]
    #[Assert\NotNull]
    #[Assert\Positive(message: 'Total seats must be a positive number.')]
    #[Groups(['session:read', 'session:write'])]
    private int $totalSeats = 0;

    #[ODM\Field(type: 'int')]
    #[Groups(['session:read'])]
    private int $availableSeats = 0;

    #[ODM\Field(type: 'bool')]
    #[Groups(['session:read'])]
    private bool $active = true;

    #[ODM\Field(type: 'date')]
    #[Groups(['session:read'])]
    #[Context(normalizationContext: [DateTimeNormalizer::FORMAT_KEY => \DateTimeInterface::ATOM])]
    private \DateTimeInterface $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?string 
    { 
        return $this->id; 
    }

    public function getLanguage(): string 
    { 
        return $this->language; 
    }

    public function setLanguage(string $language): static 
    { 
        $this->language = $language; 
        return $this; 
    }

    public function getDate(): ?\DateTimeInterface 
    { 
        return $this->date; 
    }

    public function setDate(\DateTimeInterface $date): static 
    { 
        $this->date = $date; 
        return $this; 
    }

    public function getTime(): string 
    { 
        return $this->time; 
    }
    public function setTime(string $time): static 
    { 
        $this->time = $time; return $this; 
    }

    public function getLocation(): string 
    { 
        return $this->location; 
    }
    public function setLocation(string $location): static 
    { 
        $this->location = $location; 
        return $this; 
    }

    public function getTotalSeats(): int 
    { 
        return $this->totalSeats; 
    }

    public function setTotalSeats(int $totalSeats): static 
    { 
        $this->totalSeats = $totalSeats; 
        return $this; 
    }

    public function getAvailableSeats(): int 
    { 
        return $this->availableSeats; 
    }
    public function setAvailableSeats(int $availableSeats): static 
    { 
        $this->availableSeats = $availableSeats; 
        return $this; 
    }

    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): static { $this->active = $active; return $this; }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function hasAvailableSeats(): bool
    { 
        return $this->availableSeats > 0; 
    }

    public function decrementAvailableSeats(): void
    {
        if ($this->availableSeats <= 0) {
            throw new \DomainException('No available seats for this session.');
        }
        --$this->availableSeats;
    }

    public function incrementAvailableSeats(): void
    {
        if ($this->availableSeats >= $this->totalSeats) {
            throw new \DomainException('Available seats cannot exceed total seats.');
        }
        ++$this->availableSeats;
    }
}
