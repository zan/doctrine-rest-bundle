<?php


namespace Zan\DoctrineRestBundle\EntityResultSet;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Annotation\PublicId;
use Zan\DoctrineRestBundle\EntityResultSet\EntityResultSetAvailableParameter;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Zan\DoctrineRestBundle\Util\DataTypeUtils;

abstract class AbstractEntityResultSet
{
    /** @var EntityManagerInterface */
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

    /**
     * todo: better docs, make into value object?
     * @var array keys are 'property' and 'direction' (ASC, DESC)
     */
    protected $orderBys = [];

    protected $firstResultOffset = 0;

    /** @var int|null Maximum number of results to return per page (null means no limit) */
    protected ?int $maxNumResults = null;

    protected ZanQueryBuilder $qb;

    protected ResultSetFilterCollection $dataFilterCollection;

    public function __construct(
        string $entityClassName,
        EntityManagerInterface $em,
    ) {
        $this->em = $em;
        $this->entityClassName = $entityClassName;
        $this->entityMetadata = $em->getClassMetadata($entityClassName);

        // todo: move to initializeQueryBuilder method so it can be called multiple times if necessary?
        $this->qb = new ZanQueryBuilder($em);
        // todo: partial select support based on field map
        //$qb->select('partial e.{id, prnOrderId}');
        $this->qb->select('e');
        $this->qb->from($this->entityClassName, 'e');

        $this->resultSetParameters = [];
        $this->dqlParameters = [];
        $this->dataFilterCollection = new ResultSetFilterCollection();
    }

    /**
     * @deprecated move to using getResultsPage() or streamResults()
     */
    public function getResults()
    {
        $qb = $this->finalizeQueryBuilder();

        $query = $qb->getQuery()
            ->setFirstResult($this->firstResultOffset)
            ->setMaxResults($this->maxNumResults);

        $result = $query->getResult();

        return $result;
    }

    /**
     * @param bool $disablePaginatorFetchJoinCollection If true, disable the Paginator's fetchJoinCollection behavior
     *      See: https://www.doctrine-project.org/projects/doctrine-orm/en/2.14/tutorials/pagination.html
     */
    public function getPagedResult(bool $disablePaginatorFetchJoinCollection = false): Paginator
    {
        $qb = $this->finalizeQueryBuilder();

        $query = $qb->getQuery()
            ->setFirstResult($this->firstResultOffset)
            ->setMaxResults($this->maxNumResults);

        $paginator = new Paginator($query, !$disablePaginatorFetchJoinCollection);

        return $paginator;
    }

    public function mustGetSingleResult()
    {
        $results = $this->getResults();
        if (count($results) !== 1) throw new \ErrorException('Expected exactly 1 result, got ' . count($results));

        return $results[0];
    }

    public function getSingleResult()
    {
        $results = $this->getResults();
        if (!$results) return null;

        return $results[0];
    }

    /**
     * @deprecated this seems to cause several odd issues
     *
     * - queries are actually slower with automatic fetch joining
     *
     * - doctrine seems to retrieve null results, eg. this API request:
     *      /api/zan/drest/entity/App.Entity.Role?responseFields=roleUserMappings&responseFields=roleUserMappings.user
     *      results in doctrine trying to load an AppUserRole entity with all null values, resulting in a type violoation
     *      for entityVersion
     */
    public function fetchJoinProperties(array $propertyPaths)
    {
        foreach ($propertyPaths as $propertyPath) {
            $resolvedProperty = $this->qb->resolveProperty($propertyPath);
            foreach ($resolvedProperty->getJoinedAliases() as $joinedAlias) {
                $this->qb->addSelect($joinedAlias);
            }
        }
    }

    /**
     * Filters the result set so that it only includes records where the identifier matches $identifier
     *
     * Identifiers are any of the following:
     *  - The doctrine ORM\Id property
     *  - Any property annotated with Zan\PublicId
     */
    public function addIdentifierFilter($identifier)
    {
        $expr = $this->em->getExpressionBuilder();

        $identifiersExpr = new Orx();

        foreach ($this->getEntityProperties() as $property) {
            $hasDoctrineId = false;
            $hasDoctrineIdAttribute = $property->getAttributes(Id::class);
            if ($hasDoctrineIdAttribute) $hasDoctrineId = true;

            $hasZanPublicId = false;
            $zanPublicIdAttrs = $property->getAttributes(PublicId::class);
            if ($zanPublicIdAttrs) {
                $hasZanPublicId = true;
            }

            if ($hasDoctrineId || $hasZanPublicId) {
                // MySQL has very weird string conversion rules, so ensure parameters are matched to the
                // appropriate type to prevent unexpected casting
                // Eg. By default, "1asdf" will be converted to `1` and match the record with ID 1
                if ($property->getType()->getName() == 'int') {
                    $identifiersExpr->add($expr->eq('e.' . $property->name, ':identifierAsInt'));
                    $this->setDqlParameter('identifierAsInt', DataTypeUtils::ensureInt($identifier));
                }
                else {
                    $identifiersExpr->add($expr->eq('e.' . $property->name, ':identifier'));
                    $this->setDqlParameter('identifier', $identifier);
                }
            }
        }

        $this->addFilterExpr($identifiersExpr);
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
     * todo: ensure this method is only called once
     */
    protected function finalizeQueryBuilder(): ZanQueryBuilder
    {
        $qb = $this->qb;

        // Add additional expressions
        foreach ($this->filterExprs as $expr) {
            $qb->andWhere($expr);
        }

        // Check for soft delete support
        if ($this->entitySupportsSoftDelete()) {
            $qb->andWhere('e.deletedAt > CURRENT_TIMESTAMP() OR e.deletedAt IS NULL');
        }

        $this->dataFilterCollection->applyToQueryBuilder($qb);

        // Add additional filters from external sources
        foreach ($this->additionalFilters as $additionalFilter) {
            $qb->andWhere($additionalFilter);
        }

        // Set parameters
        foreach ($this->dqlParameters as $name => $value) {
            $qb->setParameter($name, $value);
        }

        $this->calculateOrderBy($qb);

        //dump($qb->getDQL());
        return $qb;
    }

    public function filterByPermissionsCalculator(PermissionsCalculatorInterface $calculator, $actingUser)
    {
        $calculator->filterQueryBuilder($this->qb, $actingUser);
    }

    protected function calculateOrderBy(ZanQueryBuilder $qb)
    {
        foreach ($this->orderBys as $rawOrderBy) {
            // NOTE: property already includes the alias, eg. e.requestedBy
            $qb->addOrderBy($rawOrderBy['property'], $rawOrderBy['direction']);
        }
    }

    protected function entitySupportsSoftDelete()
    {
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

    public function addOrderBy(string $property, string $direction = 'ASC')
    {
        $resolved = $this->qb->resolveProperty($property);

        // Normalize property to something that can be sorted in DQL
        $fieldName = $resolved->getQueryAlias();

        // If this is a column that makes sense to "natural sort" then implement this by adding a length orderBy
        if ($resolved->isEligibleForNaturalSort()) {
            $this->orderBys[] = [ 'property' => 'LENGTH(' . $fieldName . ')', 'direction' => $direction ];
        }

        $this->orderBys[] = [ 'property' => $fieldName, 'direction' => $direction ];
    }
}
