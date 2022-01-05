<?php

namespace App\DataFixtures;

use App\Entity\Group;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class GroupFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $raw) {            
            $entity = new Group($raw['label']);
            
            $manager->persist($entity);
            $this->setReference(Group::class . '.' . $raw['label'], $entity);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [
                'label' => 'Test Group',
            ],
        ];
    }
}