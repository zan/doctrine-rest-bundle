<?php

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class UserGroupFixtures extends Fixture implements DependentFixtureInterface
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
            $entity = new UserGroup($raw['user'], $raw['group']);

            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [
                'user' => $this->getReference(User::class . '.test1'),
                'group' => $this->getReference(Group::class . '.Test Group'),
            ],
            [
                'user' => $this->getReference(User::class . '.test2'),
                'group' => $this->getReference(Group::class . '.Test Group'),
            ],
        ];
    }
}