<?php

namespace App\DataFixtures;

use App\Document\Session;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SessionFixtures extends Fixture
{
    /** Reference keys for sessions that ReservationFixtures will book. */
    public const SESSION_ENGLISH_1_REF = 'session_english_1';
    public const SESSION_FRENCH_1_REF  = 'session_french_1';

    private const SESSIONS = [
        [
            'ref'        => self::SESSION_ENGLISH_1_REF,
            'language'   => 'English',
            'date'       => '2026-06-15',
            'time'       => '09:00',
            'location'   => 'Paris — Centre Rivoli',
            'totalSeats' => 20,
        ],
        [
            'ref'        => self::SESSION_FRENCH_1_REF,
            'language'   => 'French',
            'date'       => '2026-06-28',
            'time'       => '10:30',
            'location'   => 'Lyon — Centre Bellecour',
            'totalSeats' => 15,
        ],
        [
            'ref'        => null,
            'language'   => 'Spanish',
            'date'       => '2026-07-10',
            'time'       => '14:00',
            'location'   => 'Bordeaux — Centre Victoire',
            'totalSeats' => 25,
        ],
        [
            'ref'        => null,
            'language'   => 'German',
            'date'       => '2026-07-25',
            'time'       => '09:30',
            'location'   => 'Strasbourg — Centre Gutenberg',
            'totalSeats' => 10,
        ],
        [
            'ref'        => null,
            'language'   => 'English',
            'date'       => '2026-08-14',
            'time'       => '11:00',
            'location'   => 'Paris — Centre Opéra',
            'totalSeats' => 30,
        ],
        [
            'ref'        => null,
            'language'   => 'Italian',
            'date'       => '2026-09-05',
            'time'       => '13:00',
            'location'   => 'Nice — Centre Masséna',
            'totalSeats' => 12,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::SESSIONS as $data) {
            $session = new Session();
            $session->setLanguage($data['language'])
                    ->setDate(new \DateTimeImmutable($data['date']))
                    ->setTime($data['time'])
                    ->setLocation($data['location'])
                    ->setTotalSeats($data['totalSeats'])
                    ->setAvailableSeats($data['totalSeats']); // starts fully open

            $manager->persist($session);

            if ($data['ref'] !== null) {
                $this->addReference($data['ref'], $session);
            }
        }

        $manager->flush();
    }
}
