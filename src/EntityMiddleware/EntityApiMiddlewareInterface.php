<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

interface EntityApiMiddlewareInterface
{
    public function beforeCreate($entity);
}