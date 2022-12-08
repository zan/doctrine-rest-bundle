<?php


namespace Zan\DoctrineRestBundle\Permissions;


/**
 * Represents a map of which records in a result set are editable and which are read-only
 */
class ResultSetDeletabilityMap
{
    /** @var PermissionsCalculatorInterface|null  */
    protected $permissionsCalculator;

    /** @var array Entity IDs that can be deleted */
    protected $deletableIds = [];

    /** @var array Entity IDs that can not be edited */
    protected $notDeletableIds = [];

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

        if ($this->permissionsCalculator->canDeleteEntity($entity, $this->actingUser)) {
            // todo: dynamically calculate entity ID field
            $this->deletableIds[] = $entity->getId();
        }
        else {
            $this->notDeletableIds[] = $entity->getId();
        }
    }

    public function getCompressedMap()
    {
        $numDeletable = count($this->deletableIds);
        $numNotDeletable = count($this->notDeletableIds);

        // If nothing is deletable, we can default to false
        if ($numDeletable === 0) return ['default' => false];

        // If everything is deletable, default to true
        if ($numDeletable > 0 && $numNotDeletable === 0) return ['default' => true];

        // Otherwise, default to whichever has the most items and then include IDs for the exceptions
        if ($numDeletable > $numNotDeletable) {
            return [
                'default' => true,
                'exceptions' => $this->notDeletableIds,
            ];
        }
        else {
            return [
                'default' => false,
                'exceptions' => $this->deletableIds,
            ];
        }
    }
}