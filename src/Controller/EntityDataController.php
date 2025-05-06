<?php


namespace Zan\DoctrineRestBundle\Controller;


use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Workflow\Registry;
use Zan\CommonBundle\Util\RequestUtils;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\CommonBundle\Util\ZanArray;
use Zan\CommonBundle\Util\ZanDebug;
use Zan\DoctrineRestBundle\Annotation\PublicId;
use Zan\DoctrineRestBundle\Api\Error;
use Zan\DoctrineRestBundle\EntityMiddleware\EntityApiMiddlewareEvent;
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
use Zan\DoctrineRestBundle\Permissions\ResultSetDeletabilityMap;
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

#[Route('/entity')]
class EntityDataController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/{entityId}', methods: ["GET"])]
    public function listEntities(
        string $entityId,
        Request $request,
        EntityManagerInterface $em,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        MinimalEntitySerializer $entitySerializer,
    ) {
        $params = RequestUtils::getParameters($request);
        $responseFields = [];
        $includeMetadata = [];
        // Collection of filters to apply to the result set
        $filterCollection = new ResultSetFilterCollection();
        $user = $this->security->getUser();

        $entityClassName = $this->unescapeEntityId($entityId);
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);
        if (!$permissionsCalculator) {
            throw new ApiException('This entity is not available for API access', Error::NO_ENTITY_PERMISSIONS);
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
            $filterCollection->addAndFiltersFromArray($decodedFilters);
        }
        // Generic free-text search on all results (eg. when typing in a combo box to filter it)
        if ($request->query->has('query') && $request->query->get('query') != '') {
            $searchString = $request->query->get('query');
            $searchFields = ZanArray::createFromString($request->query->get('queryFields'));
            if (!$searchFields) throw new ApiException('"queryFields" must be specified when "query" parameter is present');

            $searchFilters = [];
            foreach ($searchFields as $field) {
                $searchFilters[] = ResultSetFilter::buildFromArray(['property' => $field, 'operator' => 'like', 'value' => $searchString]);
            }
            $filterCollection->addOrFilters($searchFilters);
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em);
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

        $entities = [];
        $metadata = [];

        $editabilityMap = null;
        $includeEditability = false;
        $includeDeletability = false;
        if (in_array('editability', $includeMetadata) || in_array('permissions', $includeMetadata)) {
            $includeEditability = true;
            $editabilityMap = new ResultSetEditabilityMap(
                $permissionsCalculator,
                $user
            );
        }
        if (in_array('deletability', $includeMetadata) || in_array('permissions', $includeMetadata)) {
            $includeDeletability = true;
            $deletabilityMap = new ResultSetDeletabilityMap(
                $permissionsCalculator,
                $user
            );
        }

        // Filter results based on permissions
        $resultSet->filterByPermissionsCalculator($permissionsCalculator, $this->security->getUser());

        // Process results
        // The query generated by doctrine for Pagination can be very expensive so allow disabling it
        // See: https://www.doctrine-project.org/projects/doctrine-orm/en/2.14/tutorials/pagination.html
        $disablePaginatorFetchJoinCollection = false;
        if (array_key_exists('disablePaginatorFetchJoinCollection', $params)) {
            $disablePaginatorFetchJoinCollection = $params['disablePaginatorFetchJoinCollection'] == 'true' ? true : false;
        }

        $pagedResults = $resultSet->getPagedResult($disablePaginatorFetchJoinCollection);
        foreach ($pagedResults as $entity) {
            $entities[] = $entitySerializer->serialize($entity, $responseFields);

            if ($includeEditability) $editabilityMap->processEntity($entity);
            if ($includeDeletability) $deletabilityMap->processEntity($entity);
        }

        if ($includeEditability) {
            $metadata['editability'] = $editabilityMap->getCompressedMap();
        }
        if ($includeDeletability) {
            $metadata['deletability'] = $deletabilityMap->getCompressedMap();
        }

        if (in_array('permissions', $includeMetadata)) {
            $canCreateEntities = $permissionsCalculator->canCreateEntity($entityClassName, $this->security->getUser(), $params);
            $metadata['canCreateEntities'] = $canCreateEntities;
        }

        return new JsonResponse([
            'success' => true,
            'total' => count($pagedResults),
            'data' => $entities,
            'metadata' => $metadata,
        ]);
    }

    #[Route('/{entityId}/{identifier}', methods: ["GET"])]
    public function getEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        MinimalEntitySerializer $serializer,
        ?Registry $workflowRegistry = null,
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->security->getUser();
        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        // Collection of filters to apply to the result set. If this is used, it must result in exactly one result
        // and identifier can be left off
        $hasFilter = false;
        $filterCollection = new ResultSetFilterCollection();

        if (!$permissionsCalculator) {
            throw new ApiException('This entity is not available for API access', Error::NO_ENTITY_PERMISSIONS);
        }

        $responseFields = [];
        $includeMetadata = [];  // which types of metadata to include in the response
        $metadata = [];         // metadata serialized to the response

        if ($request->query->has('includeMetadata')) {
            $includeMetadata = ZanArray::createFromString($params['includeMetadata']);
        }
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }
        if ($request->query->has('filter')) {
            $hasFilter = true;
            // This parameter is json-encoded
            $decodedFilters = json_decode($request->query->get('filter'), true);
            $filterCollection->addAndFiltersFromArray($decodedFilters);
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
        $includeDeletability = false;
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
                $permissionsCalculator,
                $user
            );
        }
        if (in_array('deletability', $includeMetadata)) {
            $includeDeletability = true;
            $deletabilityMap = new ResultSetDeletabilityMap(
                $permissionsCalculator,
                $user
            );
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em);
        $resultSet->setActingUser($user);
        $resultSet->setDataFilterCollection($filterCollection);
        if (!$hasFilter && $identifier) $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->getSingleResult();
        if (!$entity) {
            return new JsonResponse([
                'success' => false,
                'code' => 'Zan.EntityNotFound',
            ], 404);
        }

        // Apply field editability information, if requested
        if ($includeEditability) {
            $editabilityMap->processEntity($entity);
            $metadata['editability'] = $editabilityMap->getCompressedMap();
        }
        if ($includeFieldEditability) {
            $propertyEditabilityMap->processEntity($entity);
            $metadata['fieldEditability'] = $propertyEditabilityMap->getCompressedMap();
        }
        if ($includeDeletability) {
            $deletabilityMap->processEntity($entity);
            $metadata['deletability'] = $deletabilityMap->getCompressedMap();
        }

        $serialized = $serializer->serialize($entity, $responseFields);

        if ($request->query->has('includeWorkflow') && $workflowRegistry !== null) {
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

    #[Route('/{entityId}/{identifier}', methods: ["PUT"])]
    public function updateEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        ApiEntityLoader $entityLoader,
        Reader $annotationReader,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        MinimalEntitySerializer $serializer,
    ) {
        $params = RequestUtils::getParameters($request);
        $entityClassName = $this->unescapeEntityId($entityId);
        $responseFields = [];
        $isBulkOperation = false;

        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        $decodedBody = json_decode($request->getContent(), true);
        $updatedEntities = [];

        // An array of records means a bulk update
        if (ZanArray::isNotMap($decodedBody)) {
            $isBulkOperation = true;

            foreach ($decodedBody as $rawEntityData) {
                $updatedEntities[] = $this->updateSingleEntity($entityClassName, $rawEntityData['id'], $rawEntityData, $entityLoader, $em, $this->security->getUser());
            }
        }
        // Updating a single entity (this returns a response with a single entity, see below)
        else {
            $updatedEntities[] = $this->updateSingleEntity($entityClassName, $identifier, $decodedBody, $entityLoader, $em, $this->security->getUser());
        }

        // Commit changes to the database
        try {
            $em->flush();
        }
        catch (OptimisticLockException $e) {
            // Graceful handling is only implemented for single entities
            if (count($updatedEntities) > 1) throw $e;

            return $this->buildConflictingEditResponse($updatedEntities[0]);
        }

        $serializedData = null;
        if ($isBulkOperation) {
            $serializedData = [];
            foreach ($updatedEntities as $updatedEntity) {
                $serializedData[] = $serializer->serialize($updatedEntity, $responseFields);
            }
        }
        else {
            $serializedData = $serializer->serialize($updatedEntities[0], $responseFields);
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
        ApiEntityLoader $entityLoader,
        EntityManagerInterface $em,
        UserInterface $user,
    ) {
        $resultSet = new GenericEntityResultSet($entityClassName, $em);
        $resultSet->setActingUser($user);
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->getSingleResult();
        if (!$entity) throw new \InvalidArgumentException("No entity found with the specified identifier");

        $entityLoader->load($entity, $rawData);

        return $entity;
    }

    #[Route('/{entityId}', methods: ["POST"])]
    public function createEntity(
        string $entityId,
        Request $request,
        EntityManagerInterface $em,
        EntityMiddlewareRegistry $middlewareRegistry,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        ApiEntityLoader $entityLoader,
        MinimalEntitySerializer $serializer,
    ) {
        $params = RequestUtils::getParameters($request);
        $decodedBody = json_decode($request->getContent(), true);
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->security->getUser();
        $middlewares = $middlewareRegistry->getMiddlewaresForEntity($entityClassName);
        $isBulkOperation = false;
        $enableDebugging = $request->get('zan_enableDebugging', false);

        $responseFields = [];
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        if (!$entityClassName) throw new \InvalidArgumentException('entityClassName is required');

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);

        // Deny access unless there's a calculator that specifically permits access
        if (!$permissionsCalculator) {
            if ($enableDebugging) {
                $prnPermissionsCalculator = '<NULL>';
                if ($permissionsCalculator) $prnPermissionsCalculator = get_class($permissionsCalculator);
                ZanDebug::dump("Using permissions calculator class: " . $prnPermissionsCalculator);
                ZanDebug::dump("Entity class name: " . $entityClassName);
                ZanDebug::dump("User: " . $user->getUsername());
            }

            throw new ApiException('This entity is not available for API access', Error::NO_ENTITY_PERMISSIONS);
        }

        $newEntitiesRaw = [];

        // An array of records means a bulk update
        if ($decodedBody && ZanArray::isNotMap($decodedBody)) {
            $isBulkOperation = true;

            foreach ($decodedBody as $rawEntityData) {
                $newEntitiesRaw[] = $rawEntityData;
            }
        }
        // Updating a single entity (this returns a response with a single entity, see below)
        else {
            $newEntitiesRaw = [ $decodedBody ];
        }

        $newEntities = [];
        foreach ($newEntitiesRaw as $rawData) {
            // Check create permissions
            if (!$permissionsCalculator->canCreateEntity($entityClassName, $user, $rawData)) {
                if ($enableDebugging) {
                    ZanDebug::dump("Not creating entity, permission denied");
                }

                // Return immediately to avoid partial bulk creates
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Insufficient permissions to create one or more entities',
                    'code' => Error::INSUFFICIENT_PERMISSIONS,
                ], 403);
            }

            $newEntity = $entityLoader->create($entityClassName, $rawData);

            $middlewareArguments = new EntityApiMiddlewareEvent($user, $rawData, $newEntity);

            // beforeCreate middleware
            foreach ($middlewares as $middleware) {
                $middleware->beforeCreate($middlewareArguments);
            }

            // Commit changes to the database
            $em->persist($newEntity);
            $newEntities[] = $newEntity;
        }

        $em->flush();

        // afterCreate middleware
        foreach ($newEntities as $newEntity) {
            $middlewareArguments = new EntityApiMiddlewareEvent($user, $rawData, $newEntity);

            foreach ($middlewares as $middleware) {
                $middleware->afterCreate($middlewareArguments);
            }
        }

        // Flush again to pick up changes in middleware
        if (count($middlewares) > 0) $em->flush();

        $serialized = [];
        if ($isBulkOperation) {
            foreach ($newEntities as $newEntity) {
                $serialized[] = $serializer->serialize($newEntity, $responseFields);
            }
        }
        else {
            $serialized = $serializer->serialize($newEntities[0], $responseFields);
        }

        $retData = [
            'success' => true,
            'data' => $serialized,
        ];

        return new JsonResponse($retData);
    }

    #[Route('/{entityId}/{identifier}', methods: ["DELETE"])]
    public function deleteEntity(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
    ) {
        $entityClassName = $this->unescapeEntityId($entityId);
        $user = $this->security->getUser();
        $enableDebugging = $request->get('zan_enableDebugging', false);

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);
        if (!$permissionsCalculator) {
            throw new ApiException('This entity is not available for API access', Error::NO_ENTITY_PERMISSIONS);
        }

        // Identifier in the query string overrides one in the route
        // ExtJs will start with an autogenerated ID but extraparams can be used to pass an
        // explicit identifier, so one in the query string should take precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em);
        $resultSet->setActingUser($user);
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->mustGetSingleResult();

        if (!$permissionsCalculator->canDeleteEntity($entity, $user)) {
            if ($enableDebugging) {
                $prnPermissionsCalculator = get_class($permissionsCalculator);
                ZanDebug::dump("Using permissions calculator class: " . $prnPermissionsCalculator);
                ZanDebug::dump("Entity class name: " . $entityClassName);
                ZanDebug::dump("User: " . $user->getUsername());
            }

            return new JsonResponse([
                'success' => false,
                'code' => Error::INSUFFICIENT_PERMISSIONS,
            ], 403);
        }

        $em->remove($entity);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    protected function buildConflictingEditResponse($entity)
    {
        $retData = [
            'success' => false,
            'errorMessage' => 'These changes conflict with a newer revision of this entity',
            'errorCode' => Error::CONFLICTING_EDITS,
            'entityNamespace' => get_class($entity),
        ];

        if (method_exists($entity, 'getId')) {
            $retData['entityKey'] = $entity->getId();
        }

        // HTTP 409 - Conflict
        return new JsonResponse($retData, 409);
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