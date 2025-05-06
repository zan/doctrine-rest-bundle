<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\ORM\EntityManagerInterface;
use Zan\DoctrineRestBundle\Annotation\ApiEnabled;

/**
 * todo: permissions calculator should be a service
 */
class EntityPropertyEditabilityMap
{
    protected static $propertyAvailableToApiCache = [];

    /** @var PermissionsCalculatorInterface|null  */
    protected $permissionsCalculator;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var array Properties that can be edited */
    protected $editableProperties = [];

    /** @var array Properties that can not be edited */
    protected $readOnlyProperties = [];

    /** @var mixed */
    protected $actingUser;

    public function __construct(
        EntityManagerInterface $em,
        PermissionsCalculatorInterface $permissionsCalculator = null,
        $actingUser
    ) {
        $this->em = $em;
        $this->permissionsCalculator = $permissionsCalculator;
        $this->actingUser = $actingUser;
    }

    public function processEntity($entity)
    {
        // Shortcut if we don't have a calculator for some reason
        if (!$this->permissionsCalculator) return;

        $classMetadata = $this->em->getClassMetadata(get_class($entity));

        // Entity properties
        foreach ($classMetadata->fieldNames as $columnName => $propertyName) {
            // Ignore properties that aren't accessible by the entity API system
            if (!$this->propertyAvailableToApi($entity, $propertyName)) continue;

            if ($this->permissionsCalculator->canEditEntityProperty($entity, $propertyName, $this->actingUser)) {
                $this->editableProperties[] = $propertyName;
            } else {
                $this->readOnlyProperties[] = $propertyName;
            }
        }

        // Associations
        foreach ($classMetadata->getAssociationMappings() as $associationMapping) {
            // Ignore properties that aren't accessible by the entity API system
            if (!$this->propertyAvailableToApi($entity, $associationMapping['fieldName'])) continue;

            if ($this->permissionsCalculator->canEditEntityProperty($entity, $associationMapping['fieldName'], $this->actingUser)) {
                $this->editableProperties[] = $associationMapping['fieldName'];
            } else {
                $this->readOnlyProperties[] = $associationMapping['fieldName'];
            }
        }
    }

    public function getCompressedMap()
    {
        $numEditable = count($this->editableProperties);
        $numReadOnly = count($this->readOnlyProperties);

        // If nothing is editable, we can default to false
        if ($numEditable === 0) return ['default' => false];

        // If everything is editable, default to true
        if ($numEditable > 0 && $numReadOnly === 0) return ['default' => true];

        // Otherwise, default to whichever has the most items and then include exceptions
        if ($numEditable > $numReadOnly) {
            return [
                'default' => true,
                'exceptions' => $this->readOnlyProperties,
            ];
        }
        else {
            return [
                'default' => false,
                'exceptions' => $this->editableProperties,
            ];
        }
    }

    /**
     * todo: shared with MinimalEntitySerializer
     */
    protected function propertyAvailableToApi($entity, $property): bool
    {
        $cacheKey = sprintf('%s::%s', get_class($entity), $property);

        if (array_key_exists($cacheKey, static::$propertyAvailableToApiCache)) {
            return static::$propertyAvailableToApiCache[$cacheKey];
        }

        $metadata = $this->em->getClassMetadata(get_class($entity));

        // ApiEnabled attribute
        $attributes = $metadata->reflFields[$property]->getAttributes(ApiEnabled::class);
        if ($attributes && $attributes[0]->getName() === ApiEnabled::class)
        {
            static::$propertyAvailableToApiCache[$cacheKey] = true;
            return true;
        }
        else {
            static::$propertyAvailableToApiCache[$cacheKey] = false;
            return false;
        }
    }
}