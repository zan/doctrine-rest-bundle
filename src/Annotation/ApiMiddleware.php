<?php

namespace Zan\DoctrineRestBundle\Annotation;

use Doctrine\ORM\Mapping\Annotation;

/**
 * Specifies an array of middleware to call (in the specified order) when interacting with this entity through the API
 *
 * @Annotation
 * @Target({"CLASS"})
 */
#[\Attribute]
class ApiMiddleware implements Annotation
{
    /** @var string[] */
    private $middlewares;

    /**
     * @param string[]|string $args
     */
    public function __construct($args)
    {
        // "value" will come from a doctrine-style annotation, otherwise use attribute-style value
        $values = $args['value'] ?? $args;

        if (!is_array($values)) $values = [ $values ];

        $this->middlewares = $values;
    }

    public function getMiddlewares()
    {
        return $this->middlewares;
    }
}