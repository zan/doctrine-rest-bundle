<?php

namespace Zan\DoctrineRestBundle\Controller;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Workflow\Registry;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\Response\WorkflowResponse;
use Zan\DoctrineRestBundle\Util\DoctrineRestUtils;

#[Route('/entity-workflow')]
class EntityWorkflowController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/{entityId}/{identifier}', methods: ["GET"])]
    public function getEntityWorkflow(
        string $entityId,
        string $identifier,
        Request $request,
        Registry $workflowRegistry,
        EntityManagerInterface $em,
    ) {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        // Give identifier in the query string precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em);
        $resultSet->setActingUser($this->security->getUser());

        // Apply identifier filter
        $resultSet->addIdentifierFilter($identifier);

        // Get the entity with the associated workflow
        $entity = $resultSet->mustGetSingleResult();
        
        $workflow = $workflowRegistry->get($entity);

        return new JsonResponse([
            'success' => true,
            'data' => (new WorkflowResponse($workflow, $entity))->toArray(),
        ]);
    }

    #[Route('/{entityId}/{identifier}/transition', methods: ["POST"])]
    public function transition(
        string $entityId,
        string $identifier,
        Request $request,
        Registry $workflowRegistry,
        EntityManagerInterface $em,
    ) {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        $transition = $request->request->get('transition');
        $decodedBody = json_decode($request->getContent(), true);

        if ($decodedBody && array_key_exists('transition', $decodedBody)) {
            $transition = $decodedBody['transition'];
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em);
        $resultSet->setActingUser($this->security->getUser());

        // Apply identifier filter
        $resultSet->addIdentifierFilter($identifier);

        // Get the entity with the associated workflow
        $entity = $resultSet->mustGetSingleResult();

        $workflow = $workflowRegistry->get($entity);

        $workflow->apply($entity, $transition);

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'data' => (new WorkflowResponse($workflow, $entity))->toArray(),
        ]);
    }
}