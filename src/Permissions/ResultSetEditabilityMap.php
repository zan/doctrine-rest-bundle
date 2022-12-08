<?php


namespace Zan\DoctrineRestBundle\Permissions;


/**
 * Represents a map of which records in a result set are editable and which are read-only
 */
class ResultSetEditabilityMap
{
    /** @var PermissionsCalculatorInterface|null  */
    protected $permissionsCalculator;

    /** @var array Entity IDs that can be edited */
    protected $editableIds = [];

    /** @var array Entity IDs that can not be edited */
    protected $readOnlyIds = [];

    /** @var mixed */
    protected $actingUser;

    // todo: $actingUser should allow nulls or be the first argument
    public function __construct(PermissionsCalculatorInterface $permissionsCalculator = null, $actingUser)
    {
        $this->permissionsCalculator = $permissionsCalculator;
        $this->actingUser = $actingUser;
    }

    public function processEntity($entity)
    {
        // Shortcut if we don't have a calculator for some reason
        if (!$this->permissionsCalculator) return;

        if ($this->permissionsCalculator->canEditEntity($entity, $this->actingUser)) {
            // todo: dynamically calculate entity ID field
            $this->editableIds[] = $entity->getId();
        }
        else {
            $this->readOnlyIds[] = $entity->getId();
        }
    }

    public function getCompressedMap()
    {
        $numEditable = count($this->editableIds);
        $numReadOnly = count($this->readOnlyIds);

        // If nothing is editable, we can default to false
        if ($numEditable === 0) return ['default' => false];

        // If everything is editable, default to true
        if ($numEditable > 0 && $numReadOnly === 0) return ['default' => true];

        // Otherwise, default to whichever has the most items and then include IDs for the exceptions
        if ($numEditable > $numReadOnly) {
            return [
                'default' => true,
                'exceptions' => $this->readOnlyIds,
            ];
        }
        else {
            return [
                'default' => false,
                'exceptions' => $this->editableIds,
            ];
        }
    }
}