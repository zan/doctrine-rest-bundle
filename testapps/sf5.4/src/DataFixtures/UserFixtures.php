<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $raw) {            
            $entity = new User($raw['username']);
            
            $entity->setDisplayName($raw['displayName'] ?? null);

            $entity->setNumFailedLogins($raw['numFailedLogins'] ?? 0);

            $manager->persist($entity);
            $this->setReference(User::class . '.' . $raw['username'], $entity);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            // Manages the test group
            [ 'username' => 'testManager', 'displayName' => 'Manager User' ],

            [ 'username' => 'test1', 'displayName' => 'First Testuser' ],
            [ 'username' => 'test2', 'displayName' => 'Second Testuser' ],

            [
                'username' => 'hasFailedLogins',
                'displayName' => 'FailedLogin ErrorUser',
                'numFailedLogins' => 2,
            ],
        ];
    }
}