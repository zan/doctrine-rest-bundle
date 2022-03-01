<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

/**
 * An empty middleware entity API middleware implementation
 */
abstract class BaseEntityApiMiddleware implements EntityApiMiddlewareInterface
{
    /**
     * Called before an entity is committed to the database
     *
     * When this method is called the entity has been created and auto-loaded from client data
     */
    public function beforeCreate(EntityApiMiddlewareEvent $middlewareEvent)
    {
        
    }

    /**
     * Called after a new entity has been committed to the database
     *
     * The entity manager is flushed after this method is called
     */
    public function afterCreate(EntityApiMiddlewareEvent $middlewareEvent)
    {

    }
}