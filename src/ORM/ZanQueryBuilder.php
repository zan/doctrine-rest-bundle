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
     * For example, "defaultGroup.label" would result in a join of the "defaultGroup" relationship and would then
     * return an alias like "DefaultGroup.label" that could be used to compare against the label value
     */
    public function resolveProperty($propertyPath): ResolvedProperty
    {
        $rootEntity = $this->getRootEntityNamespace();
        $pathQueue = explode('.', $propertyPath);

        // Early exit if there are no dots in the property path since that means it's directly on the entity being queried
        if (count($pathQueue) === 1) {
            $classMetadata = $this->getEntityManager()->getClassMetadata($rootEntity);
            if (!array_key_exists($propertyPath, $classMetadata->fieldNames)) {
                throw new \InvalidArgumentException($rootEntity . ' does not have property "' . $propertyPath . '"');
            }

            return new ResolvedProperty(
                $this->getRootAlias() . '.' . $propertyPath,
                $classMetadata->getReflectionProperty($propertyPath),
                $classMetadata->getTypeOfField($propertyPath)
            );
        }

        $currEntity = $rootEntity;
        // Start with the entity alias
        $joinTableName = $this->getRootAlias();

        $resolvedProperty = new ResolvedProperty();
        while (count($pathQueue) > 0) {
            $currProperty = $pathQueue[0];
            $classMetadata = $this->getEntityManager()->getClassMetadata($currEntity);

            // Check if it's an association
            if ($classMetadata->hasAssociation($currProperty)) {
                $association = $classMetadata->getAssociationMapping($currProperty);
                // Associations need to be joined
                $joinAlias = $this->buildJoinAlias($currEntity, $currProperty, $resolvedProperty);
                $this->leftJoin($joinTableName . '.' . $currProperty, $joinAlias);
                // Update $currEntity so that it refers to the entity that was just joined
                $currEntity = $association['targetEntity'];
                // Next join table will be the alias we just created
                $joinTableName = $joinAlias;

                // If this is the last property to resolve, return it
                if (count($pathQueue) === 1) {
                    $resolvedProperty->setQueryAlias($joinAlias);
                    $resolvedProperty->setFieldType($classMetadata->getTypeOfField($currProperty));
                    $resolvedProperty->setReflectionProperty($classMetadata->getReflectionProperty($currProperty));
                    return $resolvedProperty;
                }
            }
            // It's a field on the entity
            else {
                $field = $classMetadata->getFieldMapping($currProperty);
                if ($field) {
                    // The field is the final node in the tree, so the most recent joinAlias can be used to query on it
                    $resolvedProperty->setQueryAlias($joinAlias . '.' . $field['fieldName']);
                    $resolvedProperty->setFieldType($classMetadata->getTypeOfField($currProperty));
                    $resolvedProperty->setReflectionProperty($classMetadata->getReflectionProperty($currProperty));
                    return $resolvedProperty;
                }
                else {
                    throw new \ErrorException("Invalid field: could not find $currProperty on $currEntity");
                }
            }

            array_shift($pathQueue);
        }

        throw new \ErrorException('Reached the end of the property specification (' . $propertyPath . ') without finding a field');
    }

    protected function buildJoinAlias(string $entityNamespace, string $property, ResolvedProperty $resolvedProperty = null): string
    {
        // Remove App\Entity\ prefix to clean things up
        $entityNamespace = ZanString::removePrefix($entityNamespace, '\\');
        $entityNamespace = ZanString::removePrefix($entityNamespace, 'App\Entity\\');

        // Replace any remaining slashes with underscores
        $entityNamespace = str_replace('\\', '_', $entityNamespace);

        // The alias is the namespace and property
        $joinAlias = $entityNamespace . '_' . $property;

        // If there's a $resolvedProperty update it with this join alias
        if ($resolvedProperty) $resolvedProperty->addJoinAlias($joinAlias);

        return $joinAlias;
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

    public function getRootAlias(): string
    {
        $rootAliases = $this->getRootAliases();
        if (count($rootAliases) !== 1) {
            throw new \ErrorException('getRootAlias called on a queryBuilder with ' . count($rootAliases) . ' root aliases');
        }

        return $rootAliases[0];
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

        $this->leftJoinHashes[$joinHash] = true;
        return parent::leftJoin($join, $alias, $conditionType, $condition, $indexBy);
    }

    protected function hasLeftJoin($joinHash)
    {
        return array_key_exists($joinHash, $this->leftJoinHashes);
    }
}