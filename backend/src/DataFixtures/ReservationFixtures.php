<?php

namespace App\DataFixtures;

use App\Document\Reservation;
use App\Document\Session;
use App\Document\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ReservationFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Run UserFixtures and SessionFixtures first so their references are available.
     */
    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            SessionFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var User $admin */
        $admin = $this->getReference(UserFixtures::ADMIN_REFERENCE, User::class);
        /** @var User $regular */
        $regular = $this->getReference(UserFixtures::REGULAR_REFERENCE, User::class);
        /** @var Session $englishSession */
        $englishSession = $this->getReference(SessionFixtures::SESSION_ENGLISH_1_REF, Session::class);
        /** @var Session $frenchSession */
        $frenchSession = $this->getReference(SessionFixtures::SESSION_FRENCH_1_REF, Session::class);

        // Admin books the English session
        $r1 = new Reservation();
        $r1->setSession($englishSession)
           ->setUserId((string) $admin->getId());
        $englishSession->decrementAvailableSeats();
        $manager->persist($r1);

        // Regular user books the French session
        $r2 = new Reservation();
        $r2->setSession($frenchSession)
           ->setUserId((string) $regular->getId());
        $frenchSession->decrementAvailableSeats();
        $manager->persist($r2);

        $manager->flush();
    }
}
