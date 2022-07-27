<?php

namespace Zan\DoctrineRestBundle\Response;

use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\TransitionBlocker;
use Symfony\Component\Workflow\Workflow;
use function Symfony\Component\Translation\t;

/**
 * Helper class for serializing a workflow
 */
class WorkflowResponse
{
    private Workflow $workflow;

    private $entity;

    public function __construct(Workflow $workflow, $entity)
    {
        $this->workflow = $workflow;
        $this->entity = $entity;
    }

    public function toArray(): array
    {
        $marking = array_keys($this->workflow->getMarking($this->entity)->getPlaces());
        // State machines only have a single place, return this outside an array
        if ($this->workflow instanceof StateMachine) {
            $marking = $marking[0];
        }

        $places = [];
        foreach ($this->workflow->getDefinition()->getPlaces() as $placeName) {
            $placeMetadata = $this->workflow->getMetadataStore()->getPlaceMetadata($placeName);
            $placeData = [
                'name' => $placeName,
            ];

            foreach ($placeMetadata as $key => $value) {
                $placeData[$key] = $value;
            }

            $places[] = $placeData;
        }

        $validGraphTransitions = [];

        foreach ($this->workflow->getDefinition()->getTransitions() as $transition) {
            // Do not include transitions that are not valid state transitions
            if (!$this->canTransitionViaGraph($transition, $this->entity)) continue;

            $transitionMetadata = $this->workflow->getMetadataStore()->getTransitionMetadata($transition);
            $blockers = $this->workflow->buildTransitionBlockerList($this->entity, $transition->getName());

            $serialized = [
                'name' => $transition->getName(),
                'label' => $transitionMetadata['label'] ?? $transition->getName(),
                'froms' => $transition->getFroms(),
                'tos' => $transition->getTos(),
            ];

            $serializedBlockers = $this->serializeTransitionBlockers($blockers);
            if ($serializedBlockers !== null) $serialized['blockers'] = $serializedBlockers;

            $validGraphTransitions[] = $serialized;
        }

        // Add in information about the entity
        $entityInfo = [
            'namespace' => get_class($this->entity),
        ];
        if (method_exists($this->entity, 'getId')) {
            $entityInfo['id'] = $this->entity->getId();
        }
        if (method_exists($this->entity, 'getEntityVersion')) {
            $entityInfo['version'] = $this->entity->getEntityVersion();
        }
        if (method_exists($this->entity, 'getWorkflowStatus')) {
            $entityInfo['workflowStatus'] = $this->entity->getWorkflowStatus();
        }

        return [
            'name' => $this->workflow->getName(),
            'entity' => $entityInfo,
            'marking' => $marking,
            'places' => $places,
            'validGraphTransitions' => $validGraphTransitions,
        ];
    }

    protected function canTransitionViaGraph(Transition $transition, $entity)
    {
        // Returns an array like IN_PROCESS => 1 so must call array_keys to get array of place strings
        $currentPlaces = array_keys($this->workflow->getMarking($entity)->getPlaces());

        foreach ($transition->getFroms() as $from) {
            if (in_array($from, $currentPlaces)) return true;
        }

        return false;
    }

    protected function serializeTransitionBlockers($blockers)
    {
        $serialized = [];
        /** @var TransitionBlocker $blocker */
        foreach ($blockers as $blocker) {
            $serialized[] = [
                'code' => $blocker->getCode(),
                'message' => $blocker->getMessage(),
                'parameters' => $blocker->getParameters(),
            ];
        }

        return $serialized ? $serialized : null;
    }
}