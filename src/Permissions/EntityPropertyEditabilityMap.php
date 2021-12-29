<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;

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

    /** @var Reader */
    protected $annotationReader;

    /** @var array Properties that can be edited */
    protected $editableProperties = [];

    /** @var array Properties that can not be edited */
    protected $readOnlyProperties = [];

    /** @var mixed */
    protected $actingUser;

    public function __construct(
        EntityManagerInterface $em,
        Reader $annotationReader,
        PermissionsCalculatorInterface $permissionsCalculator = null,
        $actingUser
    ) {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
        $this->permissionsCalculator = $permissionsCalculator;
        $this->actingUser = $actingUser;
    }

    public function processEntity($entity)
    {
        dump("processing with");
        dump(get_class($this->permissionsCalculator));
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

        // todo: associations?
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

        $annotations = $this->annotationReader->getPropertyAnnotations($metadata->reflFields[$property]);

        $isAvailable = false;
        foreach ($annotations as $annotation) {
            $className = get_class($annotation);

            // Zan Doctrine API
            if ('Zan\DoctrineRestBundle\Annotation\ApiEnabled' === $className) {
                $isAvailable = true;
                break;
            }
        }

        static::$propertyAvailableToApiCache[$cacheKey] = $isAvailable;
        return $isAvailable;
    }
}