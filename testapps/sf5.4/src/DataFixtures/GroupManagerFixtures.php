<?php

namespace App\DataFixtures;

use App\Entity\Group;
use App\Entity\User;
use App\Entity\UserGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class GroupManagerFixtures extends Fixture implements DependentFixtureInterface
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
            $raw['group']->setManager($raw['manager']);

            $manager->persist($raw['group']);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [
                'group' => $this->getReference(Group::class . '.Test Group'),
                'manager' => $this->getReference(User::class . '.testManager'),
            ],
        ];
    }
}