<?php


namespace Zan\DoctrineRestBundle\EntityResultSet;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Annotation\PublicId;
use Zan\DoctrineRestBundle\EntityResultSet\EntityResultSetAvailableParameter;
use Zan\DoctrineRestBundle\ORM\ZanQueryBuilder;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractEntityResultSet
{
    /** @var EntityManagerInterface */
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

    /**
     * todo: better docs, make into value object?
     * @var array keys are 'property' and 'direction' (ASC, DESC)
     */
    protected $orderBys = [];

    protected $firstResultOffset = 0;
    protected $maxNumResults = 100;

    protected ZanQueryBuilder $qb;

    protected ResultSetFilterCollection $dataFilterCollection;

    public function __construct(
        string $entityClassName,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
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

    public function getPagedResult(): Paginator
    {
        $qb = $this->finalizeQueryBuilder();

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
            $hasDoctrineId = ZanAnnotation::hasPropertyAnnotation(
                $this->annotationReader,
                Id::class,
                $this->getEntityClassName(),
                $property->name
            );

            $hasDoctrineIdAttribute = $property->getAttributes(Id::class);
            if ($hasDoctrineIdAttribute) $hasDoctrineId = true;

            $hasZanPublicId = false;
            $zanPublicIdAttrs = $property->getAttributes(PublicId::class);
            if ($zanPublicIdAttrs) {
                $hasZanPublicId = true;
            }

            // Fall back to annotations
            if (!$hasZanPublicId) {
                $hasZanPublicId = ZanAnnotation::hasPropertyAnnotation(
                    $this->annotationReader,
                    PublicId::class,
                    $this->getEntityClassName(),
                    $property->name
                );
            }

            if ($hasDoctrineId || $hasZanPublicId) {
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
        $hasManualOrderBy = false;
        foreach ($this->orderBys as $rawOrderBy) {
            // NOTE: property already includes the alias, eg. e.requestedBy
            $qb->addOrderBy($rawOrderBy['property'], $rawOrderBy['direction']);
            $hasManualOrderBy = true;
        }

        // Exit now if there was a manual "order by", otherwise see if the entity supports automatic sorting
        if ($hasManualOrderBy) return;

        $classInterfaces = class_implements($this->entityClassName);

        // Gedmo / STOF Sortable
        if (in_array('Gedmo\Sortable\Sortable', $classInterfaces)) {
            $properties = ZanObject::getProperties($this->entityClassName);
            $sortableProperty = null;
            foreach ($properties as $property) {
                if (ZanAnnotation::hasPropertyAnnotation($this->annotationReader, 'Gedmo\Mapping\Annotation\SortablePosition', $this->entityClassName, $property->getName())) {
                    $sortableProperty = $property->getName();
                    break;
                }
            }

            // Found the sortable property, add it to the "order by"
            if ($sortableProperty) {
                $qb->addOrderBy($qb->getRootAlias() . '.' . $sortableProperty);
            }
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

    public function addOrderBy(string $property, string $direction = 'ASC')
    {
        // Normalize property to something that can be sorted in DQL
        $fieldName = null;
        // A dot in the property means this is a nested assocation that needs to be resolved
        if (str_contains($property, '.')) {
            $fieldName = $this->qb->resolveProperty($property)->getQueryAlias();
        }
        // Otherwise, it's a simple field directly on the entity
        else {
            // The field name in the query needs to have the root alias prepended, eg e.status instead of just 'status'
            $fieldName = $this->qb->getRootAlias() . '.' . $property;
        }

        // todo: useful exception if this can't be resolved

        // todo: check for duplicates?
        $this->orderBys[] = [ 'property' => $fieldName, 'direction' => $direction ];
    }
}
