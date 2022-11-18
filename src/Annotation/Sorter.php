<?php


namespace Zan\DoctrineRestBundle\Annotation;


use Doctrine\ORM\Mapping\Annotation;

/**
 * Allows configuring how this field is sorted when retrieved from the database
 *
 * @Annotation
 * @Target({"PROPERTY"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Sorter implements Annotation
{
    const STRATEGY_DEFAULT  = 'DEFAULT';    // Default sort as implemented by the database
    const STRATEGY_NATURAL  = 'NATURAL';    // Natural sorting, ie. alphanumeric values will sort correctly

    private string $strategy;

    public function __construct($strategy = self::STRATEGY_NATURAL)
    {
        $this->strategy = $strategy;
    }

    public function getStrategy(): string
    {
        return $this->strategy;
    }
}