<?php

namespace App\DataFixtures;

use App\Entity\NoApiAccess;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class NoApiAccessFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $raw) {
            $entity = new NoApiAccess();
            
            $entity->setPublicId($raw['publicId'] ?? null);

            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [ 'publicId' => 'noApiAccess1' ],
        ];
    }
}