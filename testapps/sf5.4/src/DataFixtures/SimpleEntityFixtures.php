<?php

namespace App\DataFixtures;

use App\Entity\SimpleEntity;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class SimpleEntityFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getData() as $raw) {            
            $entity = new SimpleEntity();

            $entity->setPublicId($raw['publicId']);
            $entity->setLabel($raw['label']);

            $manager->persist($entity);
        }

        $manager->flush();
    }

    public function getData(): array
    {
        return [
            [ 'publicId' => 'simple1', 'label' => 'simple entity one', ],
        ];
    }
}