<?php


namespace Zan\DoctrineRestBundle\Util;


use Doctrine\ORM\QueryBuilder;

/**
 * todo: implement enforceUniqueParameters
 */
class ZanQueryBuilder extends QueryBuilder
{
    /** @var int Unique index for preventing parameter collisions */
    private $uniqueIdx = 0;

    public function makeUniqueParameter($key)
    {
        $key = $key . $this->uniqueIdx;
        $this->uniqueIdx++;

        return $key;
    }
}