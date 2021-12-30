<?php

namespace Zan\DoctrineRestBundle\Controller;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\Registry;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\Response\WorkflowResponse;
use Zan\DoctrineRestBundle\Util\DoctrineRestUtils;

/**
 * @Route("/entity-workflow")
 */
class EntityWorkflowController extends AbstractController
{
    /**
     * @Route("/{entityId}/{identifier}", methods={"GET"})
     */
    public function getEntityWorkflow(
        string $entityId,
        string $identifier,
        Request $request,
        Registry $workflowRegistry,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        // Give identifier in the query string precedence
        if ($request->query->has('identifier')) {
            $identifier = $request->query->get('identifier');
        }

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($this->getUser());

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

    /**
     * @Route("/{entityId}/{identifier}/transition", methods={"POST"})
     */
    public function transition(
        string $entityId,
        string $identifier,
        Request $request,
        Registry $workflowRegistry,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $entityClassName = DoctrineRestUtils::unescapeEntityId($entityId);
        $newPlace = $request->request->get('newPlace');

        $resultSet = new GenericEntityResultSet($entityClassName, $em, $annotationReader);
        $resultSet->setActingUser($this->getUser());

        // Apply identifier filter
        $resultSet->addIdentifierFilter($identifier);

        // Get the entity with the associated workflow
        $entity = $resultSet->mustGetSingleResult();

        $workflow = $workflowRegistry->get($entity);

        $workflow->apply($entity, $newPlace);

        $em->flush();

        return new JsonResponse([
            'success' => true,
            'data' => (new WorkflowResponse($workflow, $entity))->toArray(),
        ]);
    }
}