<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\ORM\QueryBuilder;

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
        $this->property = $property;
        $this->value = $value;
        $this->operator = $operator;
    }

    public function applyToQueryBuilder(QueryBuilder $qb)
    {
        $dqlSafeValue = $qb->getEntityManager()->getConnection()->quote($this->value);

        if ($this->operator == '=') {
            $qb->andWhere('e.' . $this->property . ' = ' . $dqlSafeValue);
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