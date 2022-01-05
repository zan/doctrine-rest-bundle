<?php


namespace Zan\DoctrineRestBundle\Controller;


use Symfony\Component\Workflow\Registry;
use Zan\CommonBundle\Util\RequestUtils;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\CommonBundle\Util\ZanArray;
use Zan\DoctrineRestBundle\Annotation\HumanReadableId;
use Zan\DoctrineRestBundle\EntityResultSet\AbstractEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilter;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilterCollection;
use Zan\DoctrineRestBundle\EntitySerializer\MinimalEntitySerializer;
use Zan\DoctrineRestBundle\Loader\ApiEntityLoader;
use Zan\DoctrineRestBundle\Permissions\EntityPropertyEditabilityMap;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\ResultSetEditabilityMap;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\Id;
use Doctrine\ORM\Query\Expr\Base;
use \Doctrine\ORM\Query\Expr\Orx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Zan\DoctrineRestBundle\Response\WorkflowResponse;

/**
 * @Route("/entity")
 */
class EntityDataController extends AbstractController
{
    /**
     * @Route("/{entityId}", methods={"GET"})
     */
    public function listEntities(
        string $entityId,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $params = RequestUtils::getParameters($request);
        $responseFields = [];
        $includeMetadata = [];
        // Collection of filters to apply to the result set
        $filterCollection = new ResultSetFilterCollection();
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;

        $entityClassName = $this->unescapeEntityId($entityId);
        $permissionsCalculatorFactory = new PermissionsCalculatorFactory($em);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        if ($request->query->has('includeMetadata')) {
            $includeMetadata = ZanArray::createFromString($params['includeMetadata']);
        }
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }
        if ($request->query->has('filter')) {
            // This parameter is json-encoded
            $decodedFilters = json_decode($request->query->get('filter'), true);
            $filterCollection = ResultSetFilterCollection::buildFromArray($decodedFilters);
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($user);
        $resultSet->setDataFilterCollection($filterCollection);

        $entitySerializer = new MinimalEntitySerializer(
            $em,
            $annotationReader
        );

        $entities = [];
        $metadata = [];

        $editabilityMap = null;
        $includeEditability = false;
        if (in_array('editability', $includeMetadata)) {
            $includeEditability = true;
            $editabilityMap = new ResultSetEditabilityMap(
                $permissionsCalculator,
                $user
            );
        }
        // todo: implement canCreateEntity

        // Process results
        foreach ($resultSet->getResults() as $entity) {
            $entities[] = $entitySerializer->serialize($entity, $responseFields);

            if ($includeEditability) {
                $editabilityMap->processEntity($entity);
            }
        }

        if ($includeEditability) {
            $metadata['editability'] = $editabilityMap->getCompressedMap();
        }

        return new JsonResponse([
            'success' => true,
            'data' => $entities,
            'metadata' => $metadata,
        ]);
    }

    /**
     * @Route("/{entityId}/{identifier}", methods={"GET"})
     */
    public function getEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
        Registry $workflowRegistry,
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;
        $permissionsCalculatorFactory = new PermissionsCalculatorFactory($em);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        $responseFields = [];
        $includeMetadata = [];  // which types of metadata to include in the response
        $metadata = [];         // metadata serialized to the response

        if ($request->query->has('includeMetadata')) {
            $includeMetadata = ZanArray::createFromString($params['includeMetadata']);
        }
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        // Identifier in the query string overrides one in the route
        // ExtJs will start with an autogenerated ID but extraparams can be used to pass an
        // explicit identifier, so one in the query string should take precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $editabilityMap = null;
        $includeEditability = false;
        $includeFieldEditability = false;
        if (in_array('editability', $includeMetadata)) {
            $includeEditability = true;
            $editabilityMap = new ResultSetEditabilityMap(
                $permissionsCalculator,
                $user
            );
        }
        if (in_array('fieldEditability', $includeMetadata)) {
            $includeFieldEditability = true;
            $propertyEditabilityMap = new EntityPropertyEditabilityMap(
                $em,
                $annotationReader,
                $permissionsCalculator,
                $user
            );
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($user);

        // Apply identifier filter
        $this->applyIdentifierFilter($resultSet, $identifier, $em, $annotationReader);

        $entity = $resultSet->mustGetSingleResult();

        // Apply field editability information, if requested
        if ($includeEditability) {
            $editabilityMap->processEntity($entity);
            $metadata['editability'] = $editabilityMap->getCompressedMap();
        }
        if ($includeFieldEditability) {
            $propertyEditabilityMap->processEntity($entity);
            $metadata['fieldEditability'] = $propertyEditabilityMap->getCompressedMap();
        }

        $serializer = new MinimalEntitySerializer(
            $em,
            $annotationReader,
        );

        $serialized = $serializer->serialize($entity, $responseFields);

        if ($request->query->has('includeWorkflow')) {
            $serialized['workflow'] = $this->serializeWorkflow($entity, $workflowRegistry);
        }

        $retData = [
            'success' => true,
            'data' => $serialized,
        ];
        if ($metadata) $retData['metadata'] = $metadata;

        return new JsonResponse($retData);
    }

    protected function serializeWorkflow($entity, Registry $workflowRegistry)
    {
        $workflow = $workflowRegistry->get($entity);

        return (new WorkflowResponse($workflow, $entity))->toArray();
    }

    /**
     * todo: moved to AbstractEntityResultSet
     */
    protected function applyIdentifierFilter(
        GenericEntityResultSet $resultSet,
        $identifier,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $expr = $em->getExpressionBuilder();

        $identifiersExpr = new Orx();

        foreach ($resultSet->getEntityProperties() as $property) {
            $hasDoctrineId = ZanAnnotation::hasPropertyAnnotation(
                $annotationReader,
                Id::class,
                $resultSet->getEntityClassName(),
                $property->name
            );

            $hasZanHumanReadableId = ZanAnnotation::hasPropertyAnnotation(
                $annotationReader,
                HumanReadableId::class,
                $resultSet->getEntityClassName(),
                $property->name
            );

            if ($hasDoctrineId || $hasZanHumanReadableId) {
                $identifiersExpr->add($expr->eq('e.' . $property->name, ':identifier'));
            }
        }

        $resultSet->addFilterExpr($identifiersExpr);
        $resultSet->setDqlParameter('identifier', $identifier);
    }

    /**
     * @Route("/{entityId}/{identifier}", methods={"PUT"})
     */
    public function updateEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader
    ) {
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;

        $permissionsCalculatorFactory = new PermissionsCalculatorFactory($em);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        $entityLoader = new ApiEntityLoader($em, $user);
        $entityLoader->setPermissionsCalculator($permissionsCalculator);

        $serializer = new MinimalEntitySerializer(
            $em,
            $annotationReader
        );

        $entity = $em->find($entityClassName, $identifier);
        if (!$entity) throw new \InvalidArgumentException("No entity found with the specified identifier");

        // Deny access unless there's a calculator that specifically permits access
        if (!$permissionsCalculator || !$permissionsCalculator->canEditEntity($entity, $user)) {
            // todo: better exceptions here and when not found
            throw new \InvalidArgumentException('User does not have permissions to edit entity');
        }

        $decodedBody = json_decode($request->getContent(), true);
        $entityLoader->load($entity, $decodedBody);

        // Commit changes to the database
        $em->flush();

        $retData = [
            'success' => true,
            'data' => $serializer->serialize($entity),
        ];

        return new JsonResponse($retData);
    }

    /**
     * @Route("/{entityId}", methods={"POST"})
     */
    public function createEntity(
        string $entityId,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader
    ) {
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;

        $permissionsCalculatorFactory = new PermissionsCalculatorFactory($em);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        $entityLoader = new ApiEntityLoader($em, $user);
        $entityLoader->setPermissionsCalculator($permissionsCalculator);

        $serializer = new MinimalEntitySerializer(
            $em,
            $annotationReader
        );

        // Deny access unless there's a calculator that specifically permits access
        if (!$permissionsCalculator || !$permissionsCalculator->canCreateEntity($entityClassName, $user)) {
            // todo: better exception here
            throw new \InvalidArgumentException('User does not have permissions to create entity');
        }

        $decodedBody = json_decode($request->getContent(), true);
        $newEntity = $entityLoader->create($entityClassName, $decodedBody);

        // Commit changes to the database
        $em->persist($newEntity);
        $em->flush();

        $retData = [
            'success',
            'data' => $serializer->serialize($newEntity),
        ];

        return new JsonResponse($retData);
    }

    /**
     * @Route("/{entityId}/{identifier}", methods={"DELETE"})
     */
    public function deleteEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader
    ) {
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->container->has('security.token_storage') ? $this->getUser() : null;

        $permissionsCalculatorFactory = new PermissionsCalculatorFactory($em);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        // Identifier in the query string overrides one in the route
        // ExtJs will start with an autogenerated ID but extraparams can be used to pass an
        // explicit identifier, so one in the query string should take precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($user);

        // Apply identifier filter
        $this->applyIdentifierFilter($resultSet, $identifier, $em, $annotationReader);

        $entity = $resultSet->mustGetSingleResult();

        $em->remove($entity);
        $em->flush();

        // todo: permission checks
        return new JsonResponse(['success' => true]);
    }

    protected function unescapeEntityId($escapedEntityId)
    {
        return str_replace('.', '\\', $escapedEntityId);
    }
}