<?php

namespace Zan\DoctrineRestBundle\EntityMiddleware;

use Doctrine\Common\Annotations\Reader;
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

    /** @var Reader */
    private $annotationReader;

    public function __construct(
        iterable $middlewares,
        Reader $annotationReader
    ) {
        $this->annotationReader = $annotationReader;

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

        $classAnnotations = $this->annotationReader->getClassAnnotations($reflClass);

        foreach ($classAnnotations as $annotation) {
            if (!$annotation instanceof ApiMiddleware) continue;

            foreach ($annotation->getMiddlewares() as $entityMiddlewareNamespace) {
                $middlewareNamespaces[] = $entityMiddlewareNamespace;
            }
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