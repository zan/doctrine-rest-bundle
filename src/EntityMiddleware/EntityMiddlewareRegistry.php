<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

use Zan\DoctrineRestBundle\Annotation\ApiMiddleware;

/**
 * This uses tagged_iterator: https://symfony.com/doc/current/service_container/tags.html#reference-tagged-services
 */
class EntityMiddlewareRegistry
{
    /**
     * todo: middleware interface?
     * @var array
     */
    private $middlewares = [];

    public function __construct(
        iterable $middlewares,
    ) {

        foreach ($middlewares as $middleware) {
            $this->middlewares[] = $middleware;
        }
    }

    /**
     * @param string|object $entity
     * @return EntityApiMiddlewareInterface[]
     * @throws \ReflectionException
     */
    public function getMiddlewaresForEntity($entity): array
    {
        $middlewares = [];

        if (!is_string($entity)) $entity = get_class($entity);

        $reflClass = new \ReflectionClass($entity);
        $middlewareNamespaces = [];

        $attributes = $reflClass->getAttributes(ApiMiddleware::class);
        if ($attributes) {
            $middlewareNamespaces = $attributes[0]->newInstance()->getMiddlewares();
        }

        // Look up the namespaces in the list of registered middleware
        foreach ($middlewareNamespaces as $namespace) {
            foreach ($this->middlewares as $middleware) {
                if (get_class($middleware) === $namespace) {
                    $middlewares[] = $middleware;
                }
            }
        }
        return $middlewares;
    }
}