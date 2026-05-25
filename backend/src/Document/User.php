<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Context;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Validator\Constraints as Assert;

#[ODM\Document(collection: 'users')]
#[ODM\UniqueIndex(keys: ['email' => 'asc'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ODM\Id]
    #[Groups(['user:read'])]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    #[Groups(['user:read', 'user:write'])]
    private string $name = '';

    #[ODM\Field(type: 'string')]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'user:write'])]
    private string $email = '';

    #[ODM\Field(type: 'string')]
    private string $password = '';

    #[ODM\Field(type: 'collection')]
    private array $roles = ['ROLE_USER'];

    #[ODM\Field(type: 'date')]
    #[Groups(['user:read'])]
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

    public function getName(): string 
    { 
        return $this->name; 
    }

    public function setName(string $name): static 
    { 
        $this->name = $name; 
        return $this; 
    }

    public function getEmail(): string 
    { 
        return $this->email; 
    }

    public function setEmail(string $email): static 
    { 
        $this->email = $email; 
        return $this; 
    }

    public function getPassword(): string 
    { 
        return $this->password; 
    }

    public function setPassword(string $password): static 
    { 
        $this->password = $password; 
        return $this; 
    }

    public function getRoles(): array
    {
        return array_unique([...$this->roles, 'ROLE_USER']);
    }

    public function setRoles(array $roles): static  
    { 
        $this->roles = $roles; 
        return $this; 
    }

    public function getCreatedAt(): \DateTimeInterface 
    { 
        return $this->createdAt; 
    }

    public function getUserIdentifier(): string 
    { 
        return $this->email; 
    }
    public function eraseCredentials(): void {}
}
