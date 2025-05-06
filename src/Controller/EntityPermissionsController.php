<?php

namespace Zan\DoctrineRestBundle\Controller;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Zan\CommonBundle\Util\RequestUtils;
use Zan\DoctrineRestBundle\Api\Error;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Response\ApiErrorResponse;
use Zan\DoctrineRestBundle\Response\ApiResponse;
use Zan\DoctrineRestBundle\Util\DoctrineRestUtils;

/**
 * Provides permissions information about an entity class or a specific entity
 */
#[Route('/entity-permissions')]
class EntityPermissionsController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Checking an entity class name without an identifier - check create permissions
     */
    #[Route('/{entityId}', methods: ["GET"])]
    public function getGeneralEntityPermissions(
        string $entityId,
        Request $request,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
    ): JsonResponse
    {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        $params = RequestUtils::getParameters($request);

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);
        if (!$permissionsCalculator) return new ApiErrorResponse(Error::NO_ENTITY_PERMISSIONS, 'No permissions calculator is defined for ' . $entityId);

        $permissions = [
            'create' => $permissionsCalculator->canCreateEntity($entityClassName, $this->security->getUser(), $params),
        ];

        return new ApiResponse($permissions);
    }

    /**
     * Retrieve permissions information about a specific entity
     */
    #[Route('/{entityId}/{identifier}', methods: ["GET"])]
    public function getSpecificEntityPermissions(
        string $entityId,
        string $identifier,
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
    ): JsonResponse
    {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        $params = RequestUtils::getParameters($request);

        $permissionsCalculator = $permissionsCalculatorFactory->getPermissionsCalculator($entityClassName);
        if (!$permissionsCalculator) return new ApiErrorResponse(Error::NO_ENTITY_PERMISSIONS, 'No permissions calculator is defined for ' . $entityId);

        // Retrieve the entity from the database
        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->addIdentifierFilter($identifier);

        $entity = $resultSet->getSingleResult();
        if (!$entity) {
            return new ApiErrorResponse(
                Error::NO_ENTITY,
                sprintf(
                    'No entity found with class "%s" and identifier "%s"',
                    $entityClassName,
                    $identifier,
                ),
                404,
            );
        }

        $permissions = [
            'edit' => $permissionsCalculator->canEditEntity($entity, $this->security->getUser()),
        ];

        return new ApiResponse($permissions);
    }
}