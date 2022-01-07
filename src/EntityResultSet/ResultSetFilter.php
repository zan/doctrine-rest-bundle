<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\ORM\QueryBuilder;
use Zan\CommonBundle\Util\ZanString;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

class ResultSetFilter
{
    private string $property;

    private string $operator;

    private $value;

    public static function buildFromArray($raw): ResultSetFilter
    {
        $f = new ResultSetFilter($raw['property'], $raw['value']);

        if (array_key_exists('operator', $raw)) $f->setOperator($raw['operator']);

        return $f;
    }

    public function __construct(string $property, $value, string $operator = '=')
    {
        $this->mustBeSupportedOperator($operator);

        $this->property = $property;
        $this->value = $value;
        $this->operator = $operator;
    }

    public function applyToQueryBuilder(QueryBuilder $qb)
    {
        // This method joins any necessary tables and returns a field name that can be used
        // in the WHERE part of the query
        $fieldName = $this->resolveProperty($this->property, $qb);

        //$dqlSafeValue = $qb->getEntityManager()->getConnection()->quote($this->value);
        $parameterName = $qb->generateUniqueParameterName($fieldName);

        if ('=' === $this->operator) {
            $qb->andWhere("$fieldName = :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('in' === $this->operator) {
            $qb->andWhere("$fieldName in (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
    }

    /**
     * Resolves a dot-separated property path into a series of joins and returns the join alias to use when comparing
     * against the field
     *
     * For example, "defaultGroup.label" would result in a join of the "defaultGroup" relationshipo and would then
     * return an alias like "DefaultGroup.label" that could be used to compare against the label value
     */
    protected function resolveProperty($propertyPath, ZanQueryBuilder $qb)
    {
        $pathQueue = explode('.', $propertyPath);

        // Early exit if there are no dots in the property path since that means it's directly on the entity being queried
        if (count($pathQueue) === 1) {
            return 'e.' . $propertyPath;
        }

        $rootEntity = $qb->getRootEntityNamespace();
        $field = array_pop($pathQueue);
        $currEntity = $rootEntity;
        // Start with the entity alias
        $joinTableName = $qb->getRootAlias();

        while (count($pathQueue) > 0) {
            $currProperty = $pathQueue[0];
            $joinAlias = $this->buildJoinAlias($currEntity, $currProperty);

            $qb->leftJoin($joinTableName . '.' . $currProperty, $joinAlias);

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

    protected function mustBeSupportedOperator($operator)
    {
        $supported = ['=', 'in'];

        if (!in_array($operator, $supported)) {
            throw new \InvalidArgumentException('Operator must be one of: ' . join(', ', $supported));
        }
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function setProperty(string $property): void
    {
        $this->property = $property;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function setOperator(string $operator): void
    {
        $this->mustBeSupportedOperator($operator);
        $this->operator = $operator;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value): void
    {
        $this->value = $value;
    }
}