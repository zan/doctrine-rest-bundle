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
        $fieldName = $qb->resolveProperty($this->property);

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