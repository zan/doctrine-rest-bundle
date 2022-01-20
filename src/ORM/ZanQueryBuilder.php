<?php

namespace Zan\DoctrineRestBundle\ORM;

use Doctrine\ORM\QueryBuilder;
use Zan\CommonBundle\Util\ZanString;

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
     * Resolves a dot-separated property path into a series of joins and returns the join alias to use when comparing
     * against the field
     *
     * For example, "defaultGroup.label" would result in a join of the "defaultGroup" relationshipo and would then
     * return an alias like "DefaultGroup.label" that could be used to compare against the label value
     */
    public function resolveProperty($propertyPath)
    {
        $pathQueue = explode('.', $propertyPath);

        // Early exit if there are no dots in the property path since that means it's directly on the entity being queried
        if (count($pathQueue) === 1) {
            return 'e.' . $propertyPath;
        }

        $rootEntity = $this->getRootEntityNamespace();
        $field = array_pop($pathQueue);
        $currEntity = $rootEntity;
        // Start with the entity alias
        $joinTableName = $this->getRootAlias();

        while (count($pathQueue) > 0) {
            $currProperty = $pathQueue[0];
            $joinAlias = $this->buildJoinAlias($currEntity, $currProperty);

            $this->leftJoin($joinTableName . '.' . $currProperty, $joinAlias);

            array_shift($pathQueue);
            // Update the join table for the next part of the path
            $joinTableName = $joinAlias;
        }

        // Use the most recent joinAlias and property to build the path to query on
        return $joinAlias . '.' . $field;
    }

    protected function buildJoinAlias(string $entityNamespace, string $property): string
    {
        // Remove App\Entity\ prefix to clean things up
        $entityNamespace = ZanString::removePrefix($entityNamespace, '\\');
        $entityNamespace = ZanString::removePrefix($entityNamespace, 'App\Entity\\');

        // Replace any remaining slashes with underscores
        $entityNamespace = str_replace('\\', '_', $entityNamespace);

        // Append property
        return $entityNamespace . '_' . $property;
    }

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

    /**
     * Ensures $name is a unique parameter within the query builder
     *
     * Returns the generated unique $name for use in DQL
     */
    public function setParameterUnique($name, $value): string
    {
        $uniqueName = $this->generateUniqueParameterName($name);
        $this->setParameter($uniqueName, $value);

        return $uniqueName;
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