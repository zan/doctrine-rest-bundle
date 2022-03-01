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

        $entity->setMiddlewareValue($entity->getLabel() . ' before create middleware');
    }

    public function afterCreate(EntityApiMiddlewareEvent $middlewareEvent)
    {
        /** @var SimpleEntity $entity */
        $entity = $middlewareEvent->getEntity();

        $entity->setMiddlewareValue($entity->getLabel() . ' after create middleware');
    }
}