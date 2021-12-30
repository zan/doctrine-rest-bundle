<?php

namespace Zan\DoctrineRestBundle\Response;

use Symfony\Component\Workflow\StateMachine;
use Symfony\Component\Workflow\Workflow;

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

        $enabledTransitions = [];
        foreach ($this->workflow->getEnabledTransitions($this->entity) as $transition) {
            $enabledTransitions[] = [
                'name' => $transition->getName(),
                'froms' => $transition->getFroms(),
                'tos' => $transition->getTos(),
            ];
        }

        return [
            'name' => $this->workflow->getName(),
            'marking' => $marking,
            'places' => $places,
            'enabledTransitions' => $enabledTransitions,
        ];
    }
}