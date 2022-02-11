<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\ORM\QueryBuilder;
use Zan\CommonBundle\Util\ZanSql;
use Zan\CommonBundle\Util\ZanString;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;

class ResultSetFilter
{
    private string $property;

    private string $operator;

    private $value;

    public static function buildFromArray($raw): ResultSetFilter
    {
        $f = new ResultSetFilter($raw['property'], $raw['value'] ?? '');

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

    public function applyToQueryBuilder(ZanQueryBuilder $qb)
    {
        $fieldName = null;
        // A dot in the property means this is a nested assocation that needs to be resolved
        if (str_contains($this->property, '.')) {
            $fieldName = $qb->resolveProperty($this->property)->getQueryAlias();
        }
        // Otherwise, it's a simple field directly on the entity
        else {
            // The field name in the query needs to have the root alias prepended, eg e.status instead of just 'status'
            $fieldName = $qb->getRootAlias() . '.' . $this->property;
        }
        
        $parameterName = $qb->generateUniqueParameterName($fieldName);

        if ('=' === $this->operator) {
            // Special case for null
            if (null === $this->value) {
                $qb->andWhere("$fieldName IS NULL");
            }
            else {
                $qb->andWhere("$fieldName = :$parameterName");
                $qb->setParameter($parameterName, $this->value);
            }
        }
        if ('in' === $this->operator) {
            $qb->andWhere("$fieldName in (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('like' === $this->operator) {
            $qb->andWhere("$fieldName like :$parameterName");
            $qb->setParameter($parameterName, ZanSql::escapeLikeParameter($this->value));
        }
    }

    protected function mustBeSupportedOperator($operator)
    {
        $supported = ['=', 'in', 'like'];

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