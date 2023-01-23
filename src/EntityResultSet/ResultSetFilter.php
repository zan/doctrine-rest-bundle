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

    /**
     * If the value can be parsed as a date this property will hold a \DateTimeImmutable
     */
    private ?\DateTimeImmutable $valueAsDate = null;

    public static function buildFromArray($raw): ResultSetFilter
    {
        $value = array_key_exists('value', $raw) ? $raw['value'] : null;
        $f = new ResultSetFilter($raw['property'], $value);

        if (array_key_exists('operator', $raw)) $f->setOperator($raw['operator']);

        return $f;
    }

    public function __construct(string $property, $value, string $operator = '=')
    {
        $this->mustBeSupportedOperator($operator);

        // If it looks like a javascript date, convert it to one that SQL will understand
        // todo: should be more reliable and check whether the property being queried is a \DateTimeInterface?
        if (is_string($value)) {
            $parsesAsDate = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
            if ($parsesAsDate !== false) {
                $value = $parsesAsDate->format('Y-m-d H:i:s');
                $this->valueAsDate = $parsesAsDate;
            }
        }

        $this->property = $property;
        $this->value = $value;
        $this->operator = $operator;
    }

    public function applyToQueryBuilder(ZanQueryBuilder $qb)
    {
        $fieldName = null;
        // A dot in the property means this is a nested association that needs to be resolved
        if (str_contains($this->property, '.')) {
            $fieldName = $qb->resolveProperty($this->property)->getQueryAlias();
        }
        // Otherwise, it's a simple field directly on the entity
        else {
            // The field name in the query needs to have the root alias prepended, eg e.status instead of just 'status'
            $fieldName = $qb->getRootAlias() . '.' . $this->property;
        }
        
        $parameterName = $qb->generateUniqueParameterName($fieldName);
        $operator = $this->operator;

        // Special case: Ext will pass '=' with a value array, this needs to be an 'IN' query
        if ('=' === $this->operator && is_array($this->value)) {
            $operator = 'in';
        }

        if ('=' === $operator || '==' === $operator) {
            // Special case for null
            if (null === $this->value) {
                $qb->andWhere("$fieldName IS NULL");
            }
            else {
                $qb->andWhere("$fieldName = :$parameterName");
                $qb->setParameter($parameterName, $this->value);
            }
        }
        if ('!=' === $operator) {
            // Special case for null
            if (null === $this->value) {
                $qb->andWhere("$fieldName IS NOT NULL");
            }
            else {
                $qb->andWhere("$fieldName != :$parameterName");
                $qb->setParameter($parameterName, $this->value);
            }
        }
        if ('>' === $operator) {
            $qb->andWhere("$fieldName > :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('>=' === $operator) {
            $qb->andWhere("$fieldName >= :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('<' === $operator) {
            $qb->andWhere("$fieldName < :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('<=' === $operator) {
            $qb->andWhere("$fieldName <= :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('in' === $operator) {
            $qb->andWhere("$fieldName IN (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('notin' === $operator) {
            $qb->andWhere("$fieldName NOT IN (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('like' === $operator) {
            $qb->andWhere("$fieldName LIKE :$parameterName");
            $qb->setParameter($parameterName, ZanSql::escapeLikeParameter($this->value));
        }
        if ('notlike' === $operator) {
            $qb->andWhere("$fieldName NOT LIKE :$parameterName");
            $qb->setParameter($parameterName, ZanSql::escapeLikeParameter($this->value));
        }
        if ('empty' === $operator) {
            $qb->andWhere("$fieldName = '' OR $fieldName IS NULL");
        }
        if ('notempty' === $operator || 'nempty' === $operator) {
            $qb->andWhere("$fieldName != '' AND $fieldName IS NOT NULL");
        }

        // Date-specific filters
        if ('onday' === $operator || 'notonday' === $operator) {
            if (null === $this->valueAsDate) throw new \InvalidArgumentException('onday and notonday require a valid date');

            $referenceDate = $this->valueAsDate;
            $startAt = $referenceDate->setTime(0, 0, 0);
            $endAt = $referenceDate->setTime(23, 59, 59); // BETWEEN is inclusive

            $startParamName = $qb->generateUniqueParameterName($fieldName . '_start');
            $endParamName = $qb->generateUniqueParameterName($fieldName . '_end');

            $sqlOperator = ($operator === 'onday') ? 'BETWEEN' : 'NOT BETWEEN';

            $qb->andWhere("$fieldName $sqlOperator :$startParamName AND :$endParamName");
            $qb->setParameter($startParamName, $startAt);
            $qb->setParameter($endParamName, $endAt);
        }
    }

    protected function mustBeSupportedOperator($operator)
    {
        $supported = ['=', '==', '!=', '>', '>=', '<', '<=', 'in', 'notin', 'like', 'notlike', 'empty', 'notempty', 'nempty'];

        // Date filters so that users can choose a day and see all results on that day
        $supported[] = 'onday';
        $supported[] = 'notonday';

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