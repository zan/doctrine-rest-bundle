<?php

namespace Zan\DoctrineRestBundle\Annotation;

use Doctrine\ORM\Mapping\Annotation;

/**
 * Specifies an array of middleware to call (in the specified order) when interacting with this entity through the API
 *
 * @Annotation
 * @Target({"CLASS"})
 */
class ApiMiddleware implements Annotation
{
    /** @var string[] */
    private $middlewares;

    public function __construct(array $args)
    {
        $values = $args['value'];

        if (!is_array($values)) $values = [ $values ];

        $this->middlewares = $values;
    }

    public function getMiddlewares()
    {
        return $this->middlewares;
    }
}