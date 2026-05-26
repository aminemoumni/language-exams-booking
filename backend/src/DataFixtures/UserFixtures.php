<?php

namespace App\DataFixtures;

use App\Document\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_REFERENCE   = 'user_admin';
    public const REGULAR_REFERENCE = 'user_regular';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $seeds = [
            [
                'ref'      => self::ADMIN_REFERENCE,
                'name'     => 'Admin ETS',
                'email'    => 'admin@ets.com',
                'password' => '0123456789',
                'roles'    => ['ROLE_ADMIN', 'ROLE_USER'],
            ],
            [
                'ref'      => self::REGULAR_REFERENCE,
                'name'     => 'User ETS',
                'email'    => 'user@ets.com',
                'password' => '0123456789',
                'roles'    => ['ROLE_USER'],
            ],
        ];

        foreach ($seeds as $seed) {
            $user = new User();
            $user->setName($seed['name'])
                 ->setEmail($seed['email'])
                 ->setRoles($seed['roles'])
                 ->setPassword($this->hasher->hashPassword($user, $seed['password']));

            $manager->persist($user);
            $this->addReference($seed['ref'], $user);
        }

        $manager->flush();
    }
}
