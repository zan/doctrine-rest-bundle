<?php

namespace Zan\DoctrineRestBundle\ORM;

use Doctrine\ORM\QueryBuilder;

/**
 * QueryBuilder with additional features:
 *  - Tracks joined entities to prevent duplicate joins
 */
class ZanQueryBuilder extends QueryBuilder
{
    /**
     * @var array Map of left join hashes that have already been added (see overridden leftJoin())
     */
    private array $leftJoinHashes = [];

    private int $uniqueParamCounter = 1;

    /**
     * Returns a unique parameter name that can be bound in the query without conflicting with other parameters
     */
    public function generateUniqueParameterName($prefix = null): string
    {
        $prefix = $prefix ?? 'value';

        // Replace invalid characters in prefix with an underscore
        $prefix = str_replace('.', '_', $prefix);

        return $prefix . $this->uniqueParamCounter++;
    }

    public function getRootEntityNamespace(): string
    {
        $rootEntities = $this->getRootEntities();
        if (count($rootEntities) !== 1) {
            throw new \ErrorException('getRootEntity called on a queryBuilder with ' . count($rootEntities) . ' root entities');
        }

        return $rootEntities[0];
    }

    /**
     * OVERRIDDEN to prevent duplicate joins
     *
     * Creates and adds a left join over an entity association to the query.
     *
     * The entities in the joined association will be fetched as part of the query
     * result if the alias used for the joined association is placed in the select
     * expressions.
     *
     * <code>
     *     $qb = $em->createQueryBuilder()
     *         ->select('u')
     *         ->from('User', 'u')
     *         ->leftJoin('u.Phonenumbers', 'p', Expr\Join::WITH, 'p.is_primary = 1');
     * </code>
     *
     * @param string                                     $join          The relationship to join.
     * @param string                                     $alias         The alias of the join.
     * @param string|null                                $conditionType The condition type constant. Either ON or WITH.
     * @param string|Expr\Comparison|Expr\Composite|null $condition     The condition for the join.
     * @param string|null                                $indexBy       The index for the join.
     *
     * @return $this
     */
    public function leftJoin($join, $alias, $conditionType = null, $condition = null, $indexBy = null)
    {
        $joinHash = $join . '-' . $alias;

        if ($this->hasLeftJoin($joinHash)) return;

        return parent::leftJoin($join, $alias, $conditionType, $condition, $indexBy);
    }

    protected function hasLeftJoin($joinHash)
    {
        return array_key_exists($joinHash, $this->leftJoinHashes);
    }
}