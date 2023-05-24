<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\ORM\Query\Expr;
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
            // Try ISO 8601
            $parsesAsDate = \DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
            // Also support DATE_RFC3339_EXTENDED (the default used by Ext, includes milliseconds)
            if ($parsesAsDate === false) {
                $parsesAsDate = \DateTimeImmutable::createFromFormat(DATE_RFC3339_EXTENDED, $value);
            }
            if ($parsesAsDate !== false) {
                // Ensure the date is using the server's timezone so format() conversion below works as expected
                $localDate = $parsesAsDate->setTimezone(new \DateTimeZone(date_default_timezone_get()));
                
                $this->valueAsDate = $parsesAsDate;
                $value = $localDate->format('Y-m-d H:i:s');
            }
        }

        $this->property = $property;
        $this->value = $value;
        $this->operator = $operator;
    }

    /**
     * @param Expr\Composite|Expr\Andx|Expr\Orx $expr
     */
    public function applyToExpr(Expr\Composite $expr, ZanQueryBuilder $qb)
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

        // Field name should never have anything besides a-z,0-9,.,_ in it
        $fieldName = preg_replace( "/[^a-z0-9._]/i", '', $fieldName);

        $parameterName = $qb->generateUniqueParameterName($fieldName);
        $operator = $this->operator;

        // Special case: Ext will pass '=' with a value array, this needs to be an 'IN' query
        if ('=' === $this->operator && is_array($this->value)) {
            $operator = 'in';
        }

        if ('=' === $operator || '==' === $operator) {
            // Special case for null
            if (null === $this->value) {
                $expr->addMultiple("$fieldName IS NULL");
            }
            else {
                $expr->addMultiple("$fieldName = :$parameterName");
                $qb->setParameter($parameterName, $this->value);
            }
        }
        if ('!=' === $operator) {
            // Special case for null
            if (null === $this->value) {
                $expr->addMultiple("$fieldName IS NOT NULL");
            }
            else {
                $expr->addMultiple("$fieldName != :$parameterName");
                $qb->setParameter($parameterName, $this->value);
            }
        }
        if ('>' === $operator) {
            $expr->addMultiple("$fieldName > :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('>=' === $operator) {
            $expr->addMultiple("$fieldName >= :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('<' === $operator) {
            $expr->addMultiple("$fieldName < :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('<=' === $operator) {
            $expr->addMultiple("$fieldName <= :$parameterName");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('in' === $operator) {
            $expr->addMultiple("$fieldName IN (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('notin' === $operator) {
            $expr->addMultiple("$fieldName NOT IN (:$parameterName)");
            $qb->setParameter($parameterName, $this->value);
        }
        if ('like' === $operator) {
            $expr->addMultiple("$fieldName LIKE :$parameterName");
            $qb->setParameter($parameterName, ZanSql::escapeLikeParameter($this->value));
        }
        if ('notlike' === $operator) {
            $expr->addMultiple("$fieldName NOT LIKE :$parameterName");
            $qb->setParameter($parameterName, ZanSql::escapeLikeParameter($this->value));
        }
        if ('empty' === $operator) {
            $expr->addMultiple("$fieldName = '' OR $fieldName IS NULL");
        }
        if ('notempty' === $operator || 'nempty' === $operator) {
            $expr->addMultiple("$fieldName != '' AND $fieldName IS NOT NULL");
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

            $expr->addMultiple("$fieldName $sqlOperator :$startParamName AND :$endParamName");
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