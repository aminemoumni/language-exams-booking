<?php

namespace App\Service;

use App\Document\User;
use App\DTO\RegisterRequest;
use App\DTO\UpdateUserRequest;
use App\Exception\AppException;
use App\Repository\UserRepository;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserService
{
    public function __construct(
        private readonly DocumentManager $dm,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    /**
     * @throws AppException 409 if the email is already taken
     */
    public function register(RegisterRequest $dto): User
    {
        if ($this->userRepository->findOneByEmail($dto->email)) {
            throw new AppException('Email already in use.', Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setName($dto->name)
             ->setEmail($dto->email)
             ->setPassword($this->hasher->hashPassword($user, $dto->password));

        $this->dm->persist($user);
        $this->dm->flush();

        return $user;
    }

    /**
     * @throws AppException 409 if the new email is already taken by another account
     */
    public function updateProfile(User $user, UpdateUserRequest $dto): User
    {
        if ($dto->email !== null && $dto->email !== $user->getEmail()) {
            if ($this->userRepository->findOneByEmail($dto->email)) {
                throw new AppException('Email already in use.', Response::HTTP_CONFLICT);
            }
            $user->setEmail($dto->email);
        }

        if ($dto->name !== null) {
            $user->setName($dto->name);
        }

        $this->dm->flush();

        return $user;
    }
}
