<?php


namespace Zan\DoctrineRestBundle\EntityResultSet;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Annotation\HumanReadableId;
use Zan\DoctrineRestBundle\EntityResultSet\EntityResultSetAvailableParameter;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractEntityResultSet
{
    /** @var EntityManager */
    protected $em;

    /** @var Reader */
    protected $annotationReader;

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

    protected $firstResultOffset = 0;
    protected $maxNumResults = 100;

    protected ResultSetFilterCollection $dataFilterCollection;

    public function __construct(
        string $entityClassName,
        EntityManager $em,
        Reader $annotationReader,
    ) {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
        $this->entityClassName = $entityClassName;
        $this->entityMetadata = $em->getClassMetadata($entityClassName);

        $this->resultSetParameters = [];
        $this->dqlParameters = [];
        $this->dataFilterCollection = new ResultSetFilterCollection();
    }

    /**
     * @deprecated move to using getResultsPage() or streamResults()
     */
    public function getResults()
    {
        $qb = $this->createQueryBuilder();

        $query = $qb->getQuery();

        $query->setMaxResults(1000);

        $result = $query->getResult();

        return $result;
    }

    public function getPagedResult(): Paginator
    {
        $qb = $this->createQueryBuilder();

        $query = $qb->getQuery()
            ->setFirstResult($this->firstResultOffset)
            ->setMaxResults($this->maxNumResults);

        $paginator = new Paginator($query);

        return $paginator;
    }

    public function mustGetSingleResult()
    {
        $results = $this->getResults();
        if (count($results) !== 1) throw new \ErrorException('Expected exactly 1 result, got ' . count($results));

        return $results[0];
    }

    /**
     * Filters the result set so that it only includes records where the identifier matches $identifier
     *
     * Identifiers are any of the following:
     *  - The doctrine ORM\Id property
     *  - Any property annotated with Zan\HumanReadableId
     */
    public function addIdentifierFilter($identifier)
    {
        $expr = $this->em->getExpressionBuilder();

        $identifiersExpr = new Orx();

        foreach ($this->getEntityProperties() as $property) {
            $hasDoctrineId = ZanAnnotation::hasPropertyAnnotation(
                $this->annotationReader,
                Id::class,
                $this->getEntityClassName(),
                $property->name
            );

            $hasZanHumanReadableId = ZanAnnotation::hasPropertyAnnotation(
                $this->annotationReader,
                HumanReadableId::class,
                $this->getEntityClassName(),
                $property->name
            );

            if ($hasDoctrineId || $hasZanHumanReadableId) {
                $identifiersExpr->add($expr->eq('e.' . $property->name, ':identifier'));
            }
        }

        $this->addFilterExpr($identifiersExpr);
        $this->setDqlParameter('identifier', $identifier);
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
            $qb->andWhere('e.deletedAt > CURRENT_TIMESTAMP() OR e.deletedAt IS NULL');
        }

        $this->dataFilterCollection->applyToQueryBuilder($qb);

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

        //dump($qb->getDQL());
        return $qb;
    }

    protected function getBaseQueryBuilder() : ZanQueryBuilder
    {
        return new ZanQueryBuilder($this->em);
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
        // todo: check for Gedmo\SoftDeleteable annotation
        try {
            ZanObject::getProperty($this->entityClassName, 'deletedAt');
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

    public function getDataFilterCollection(): ResultSetFilterCollection
    {
        return $this->dataFilterCollection;
    }

    public function setDataFilterCollection(ResultSetFilterCollection $dataFilterCollection): void
    {
        $this->dataFilterCollection = $dataFilterCollection;
    }

    public function getFirstResultOffset(): int
    {
        return $this->firstResultOffset;
    }

    public function setFirstResultOffset(int $firstResultOffset): void
    {
        $this->firstResultOffset = $firstResultOffset;
    }

    public function getMaxNumResults(): int
    {
        return $this->maxNumResults;
    }

    public function setMaxNumResults(int $maxNumResults): void
    {
        $this->maxNumResults = $maxNumResults;
    }
}