<?php

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserPreferenceFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [
            UserFixtures::class,
            GroupFixtures::class,
        ];
    }

    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $raw) {
            /** @var User $user */
            $user = $raw['user'];

            $user->setDefaultGroup($raw['defaultGroup'] ?? null);

            $manager->persist($user);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [
                'user' => $this->getReference(User::class . '.test1'),
                'defaultGroup' => $this->getReference(Group::class . '.Test Group'),
            ],
            [
                'user' => $this->getReference(User::class . '.test2'),
                'defaultGroup' => $this->getReference(Group::class . '.Test Group'),
            ],
        ];
    }
}