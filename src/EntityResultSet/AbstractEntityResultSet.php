<?php


namespace Zan\DoctrineRestBundle\EntityResultSet;


use Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\EntityResultSet\EntityResultSetAvailableParameter;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractEntityResultSet
{
    /** @var EntityManager */
    protected $em;

    /** @var array */
    protected $resultSetParameters;

    /** @var array Parameters passed to the DQL expression */
    protected $dqlParameters;

    /** @var string */
    protected $entityClassName;

    /** @var ClassMetadata */
    protected $entityMetadata;

    /** @var mixed */
    protected $actingUser;

    /** @var string[]  */
    protected $additionalFilters = [];

    /**
     * Additional filters included in the DQL expression
     * todo: typehint
     * @var array
     */
    protected $filterExprs = [];

    public function __construct(
        EntityManager $em,
        string $entityClassName
    ) {
        $this->em = $em;
        $this->entityClassName = $entityClassName;
        $this->entityMetadata = $em->getClassMetadata($entityClassName);

        $this->resultSetParameters = [];
        $this->dqlParameters = [];
    }

    public function getResults()
    {
        $qb = $this->createQueryBuilder();
        return $qb->getQuery()->getResult();
    }

    public function mustGetSingleResult()
    {
        $results = $this->getResults();
        if (count($results) !== 1) throw new \ErrorException('Expected exactly 1 result, got ' . count($results));

        return $results[0];
    }

    public function addFilterDql($dql, $parameters = [])
    {
        // todo: should cache these since QB isn't ready to be built until getResults()
        $this->additionalFilters[] = $dql;
        // todo: parameters
    }

    public function addFilterExpr($expr)
    {
        $this->filterExprs[] = $expr;
    }

    public function getResultSetParameter($name)
    {
        return $this->resultSetParameters[$name] ?? null;
    }

    public function setResultSetParameter($name, $value)
    {
        $this->resultSetParameters[$name] = $value;
    }

    public function setDqlParameter($name, $value)
    {
        $this->dqlParameters[$name] = $value;
    }

    public function getAvailableParameters()
    {
        return [
            new EntityResultSetAvailableParameter('entity', 'Fully-qualified namespace to the entity to use')
        ];
    }

    /**
     * todo: probably shouldn't be public, have some other way of debugging?
     * @return QueryBuilder
     */
    public function createQueryBuilder()
    {
        $qb = $this->getBaseQueryBuilder();
        // todo: partial select support based on field map
        //$qb->select('partial e.{id, prnOrderId}');
        $qb->select('e');
        $qb->from($this->entityClassName, 'e');

        // Add additional expressions
        foreach ($this->filterExprs as $expr) {
            $qb->andWhere($expr);
        }

        // Check for soft delete support
        if ($this->entitySupportsSoftDelete()) {
            $qb->andWhere('e.isDeleted = false');
        }

        // Apply permissions filters, if present
        $this->applyPermissionsFilters($qb);

        // Add additional filters from external sources
        foreach ($this->additionalFilters as $additionalFilter) {
            $qb->andWhere($additionalFilter);
        }

        // Set parameters
        foreach ($this->dqlParameters as $name => $value) {
            $qb->setParameter($name, $value);
        }

        //QuickLogger::log($qb);
        return $qb;
    }

    protected function getBaseQueryBuilder()
    {
        return $this->em->createQueryBuilder();
    }

    protected function applyPermissionsFilters(QueryBuilder $qb)
    {
        $permsCalculator = (new PermissionsCalculatorFactory($qb->getEntityManager()))
            ->getPermissionsCalculator($this->entityClassName);

        // No registered permissions classes
        if (null === $permsCalculator) return;

        // todo: interface
        /** @var $permsCalculator PermissionsCalculatorInterface */

        $permExpr = $permsCalculator->getViewExpr($qb, $this->getActingUser());

        // Default to allowing view, so a return value of true (or no return value) means do not filter the QB
        if ($permExpr === true || $permExpr === null) return;

        // false means the user has no permissions, so short circuit the query
        if ($permExpr === false) {
            $qb->andWhere('0 = 1');
        }
        // Calculator has specified a complex expression to apply
        else {
            $qb->andWhere($permExpr);
        }
    }

    protected function entitySupportsSoftDelete()
    {
        // todo: use entity metadata to make sure 'isDeleted' is a column
        try {
            ZanObject::getProperty($this->entityClassName, 'isDeleted');
            return true;
        } catch (\ErrorException $e) {

        }

        return false;
    }

    /**
     * @return array|\ReflectionProperty[]
     */
    public function getEntityProperties()
    {
        return ZanObject::getProperties($this->entityClassName);
    }

    public function getEntityClassName(): string
    {
        return $this->entityClassName;
    }

    public function getActingUser()
    {
        return $this->actingUser;
    }

    public function setActingUser($actingUser): void
    {
        $this->actingUser = $actingUser;
    }
}