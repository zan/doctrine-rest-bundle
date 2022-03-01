<?php


namespace Zan\DoctrineRestBundle\Controller;


use Modules\PosterPrintingBundle\EntityApi\PosterPrintingRequestMiddleware;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Workflow\Registry;
use Zan\CommonBundle\Util\RequestUtils;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\CommonBundle\Util\ZanArray;
use Zan\CommonBundle\Util\ZanDebug;
use Zan\DoctrineRestBundle\Annotation\PublicId;
use Zan\DoctrineRestBundle\EntityMiddleware\EntityMiddlewareRegistry;
use Zan\DoctrineRestBundle\EntityResultSet\AbstractEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilter;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilterCollection;
use Zan\DoctrineRestBundle\EntitySerializer\MinimalEntitySerializer;
use Zan\DoctrineRestBundle\Exception\ApiException;
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
        PermissionsCalculatorFactory $permissionsCalculatorFactory
    ) {
        $params = RequestUtils::getParameters($request);
        $responseFields = [];
        $includeMetadata = [];
        // Collection of filters to apply to the result set
        $filterCollection = new ResultSetFilterCollection();
        $user = $this->getUser();

        $entityClassName = $this->unescapeEntityId($entityId);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);
        if (!$permissionsCalculator) {
            throw new ApiException('This entity is not available for API access', 'Zan.Drest.NoPermissionsOnEntity');
        }

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

        // Apply URL parameters to result set
        if ($request->query->has('start')) {
            $resultSet->setFirstResultOffset($request->query->get('start'));
        }
        if ($request->query->has('limit')) {
            $resultSet->setMaxNumResults($request->query->get('limit'));
        }
        if ($request->query->has('sort')) {
            $this->applySortParameter($resultSet, $request->query->get('sort'));
        }

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

        // Filter results based on permissions
        $resultSet->filterByPermissionsCalculator($permissionsCalculator, $this->getUser());

        // Process results
        $pagedResults = $resultSet->getPagedResult();
        foreach ($pagedResults as $entity) {
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
            'total' => count($pagedResults),
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
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        Registry $workflowRegistry = null
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->getUser();
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
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->getSingleResult();
        if (!$entity) throw new \InvalidArgumentException('Could not find entity');

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
     * @Route("/{entityId}/{identifier}", methods={"PUT"})
     */
    public function updateEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
        PermissionsCalculatorFactory $permissionsCalculatorFactory
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $responseFields = [];

        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        $serializer = new MinimalEntitySerializer(
            $em,
            $annotationReader
        );

        $decodedBody = json_decode($request->getContent(), true);
        $updatedEntities = [];

        // An array of records means a bulk update
        if (ZanArray::isNotMap($decodedBody)) {
            foreach ($decodedBody as $rawEntityData) {
                $updatedEntities[] = $this->updateSingleEntity($entityClassName, $rawEntityData['id'], $rawEntityData, $permissionsCalculator, $em, $annotationReader);
            }
        }
        // Updating a single entity (this returns a response with a single entity, see below)
        else {
            $updatedEntities[] = $this->updateSingleEntity($entityClassName, $identifier, $decodedBody, $permissionsCalculator, $em, $annotationReader);
        }

        // Commit changes to the database
        $em->flush();

        $serializedData = null;
        // Single entity updated
        if (count($updatedEntities) === 1) {
            $serializedData = $serializer->serialize($updatedEntities[0], $responseFields);
        }
        // Bulk update
        else {
            $serializedData = [];
            foreach ($updatedEntities as $updatedEntity) {
                $serializedData[] = $serializer->serialize($updatedEntity, $responseFields);
            }
        }

        $retData = [
            'success' => true,
            'data' => $serializedData,
        ];

        return new JsonResponse($retData);
    }

    protected function updateSingleEntity(
        $entityClassName,
        $identifier,
        $rawData,
        $permissionsCalculator,
        $em,
        $annotationReader
    ) {
        $user = $this->getUser();

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($user);
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->getSingleResult();
        if (!$entity) throw new \InvalidArgumentException("No entity found with the specified identifier");

        // Deny access unless there's a calculator that specifically permits access
        if (!$permissionsCalculator || !$permissionsCalculator->canEditEntity($entity, $user)) {
            // todo: better exceptions here and when not found
            throw new \InvalidArgumentException('User does not have permissions to edit entity');
        }

        $entityLoader = new ApiEntityLoader($em, $user);
        $entityLoader->setPermissionsCalculator($permissionsCalculator);

        $entityLoader->load($entity, $rawData);

        return $entity;
    }

    /**
     * @Route("/{entityId}", methods={"POST"})
     */
    public function createEntity(
        string $entityId,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
        EntityMiddlewareRegistry $middlewareRegistry,
        PermissionsCalculatorFactory $permissionsCalculatorFactory
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->getUser();
        $middlewares = $middlewareRegistry->getMiddlewaresForEntity($entityClassName);

        $responseFields = [];
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        if (!$entityClassName) throw new \InvalidArgumentException('entityClassName is required');

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        $entityLoader = new ApiEntityLoader($em, $user);
        $entityLoader->setPermissionsCalculator($permissionsCalculator);

        $serializer = new MinimalEntitySerializer(
            $em,
            $annotationReader
        );

        // Deny access unless there's a calculator that specifically permits access
        if (!$permissionsCalculator || !$permissionsCalculator->canCreateEntity($entityClassName, $user)) {
            $prnPermissionsCalculator = '<NULL>';
            if ($permissionsCalculator) $prnPermissionsCalculator = get_class($permissionsCalculator);
            ZanDebug::dump("Using permissions calculator class: " . $prnPermissionsCalculator);
            ZanDebug::dump("Entity class name: " . $entityClassName);
            ZanDebug::dump("User: " . $user->getUsername());
            // todo: better exception here
            throw new \InvalidArgumentException('User does not have permissions to create entity');
        }

        $decodedBody = json_decode($request->getContent(), true);
        $newEntity = $entityLoader->create($entityClassName, $decodedBody);

        // beforeCreate middleware
        foreach ($middlewares as $middleware) {
            $middleware->beforeCreate($newEntity);
        }

        // Commit changes to the database
        $em->persist($newEntity);
        $em->flush();

        $retData = [
            'success' => true,
            'data' => $serializer->serialize($newEntity, $responseFields),
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
        Reader $annotationReader,
        PermissionsCalculatorFactory $permissionsCalculatorFactory
    ) {
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->getUser();

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        // Identifier in the query string overrides one in the route
        // ExtJs will start with an autogenerated ID but extraparams can be used to pass an
        // explicit identifier, so one in the query string should take precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($user);
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->mustGetSingleResult();

        $em->remove($entity);
        $em->flush();

        // todo: permission checks
        return new JsonResponse(['success' => true]);
    }

    protected function applySortParameter(AbstractEntityResultSet $resultSet, $rawFilter)
    {
        $decoded = json_decode($rawFilter, true);
        foreach ($decoded as $rawSort) {
            $resultSet->addOrderBy($rawSort['property'], $rawSort['direction']);
        }
    }

    protected function unescapeEntityId($escapedEntityId)
    {
        return str_replace('.', '\\', $escapedEntityId);
    }
}