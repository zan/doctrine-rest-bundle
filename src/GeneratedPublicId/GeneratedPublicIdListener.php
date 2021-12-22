<?php

namespace Zan\DoctrineRestBundle\GeneratedPublicId;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Zan\DoctrineRestBundle\Entity\GeneratedPublicIdStateEntry;

class GeneratedPublicIdListener
{
    public function postPersist($entity, LifecycleEventArgs $args)
    {
        if (!$entity instanceof GeneratedPublicIdInterface) throw new \InvalidArgumentException(get_class($entity) . " must be an instance of GeneratedPublicIdInterface");

        $em = $args->getEntityManager();

        // Early exit if this entity already has a publicId
        if ($entity->getPublicId() !== null) return;

        // Load the state for this entity (or create a new state entry)
        $publicIdStateEntry = $em->getRepository(GeneratedPublicIdStateEntry::class)
            ->findOneBy(['entityNamespace' => get_class($entity)]);

        if (!$publicIdStateEntry) {
            $publicIdStateEntry = new GeneratedPublicIdStateEntry(get_class($entity));
            $em->persist($publicIdStateEntry);
        }

        // Apply state to entity
        $entity->applyGeneratedIdState($publicIdStateEntry);
        
        $em->flush();
    }
}