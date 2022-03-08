<?php

namespace App\EntityApiMiddleware;

use App\Entity\SimpleEntity;
use Zan\DoctrineRestBundle\EntityMiddleware\BaseEntityApiMiddleware;
use Zan\DoctrineRestBundle\EntityMiddleware\EntityApiMiddlewareEvent;

class SimpleEntityMiddleware extends BaseEntityApiMiddleware
{
    public function beforeCreate(EntityApiMiddlewareEvent $middlewareEvent)
    {
        /** @var SimpleEntity $entity */
        $entity = $middlewareEvent->getEntity();

        // Append string indicating the entity was processed
        $entity->setMiddlewareValue($entity->getMiddlewareValue() . 'BEFORE_CREATE ');
    }

    public function afterCreate(EntityApiMiddlewareEvent $middlewareEvent)
    {
        /** @var SimpleEntity $entity */
        $entity = $middlewareEvent->getEntity();

        // Append string indicating the entity was processed
        $entity->setMiddlewareValue($entity->getMiddlewareValue() . 'AFTER_CREATE ');
    }
}