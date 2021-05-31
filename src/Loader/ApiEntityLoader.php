<?php


namespace Zan\DoctrineRestBundle\Loader;


use \Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Loader\EntityPropertyMetadata;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;

class ApiEntityLoader
{
    /** @var EntityManager */
    protected $em;

    /** @var ?PermissionsCalculatorInterface */
    protected $permissionsCalculator;

    /** @var mixed */
    protected $actingUser;

    public function __construct(EntityManager $em, $actingUser)
    {
        $this->em = $em;
        $this->actingUser = $actingUser;
    }

    public function create($entityClassName, $rawInput)
    {
        // todo: call entity constructor and load
        // todo: constructor args
        $newEntity = new $entityClassName;

        $this->load($newEntity, $rawInput);

        return $newEntity;
    }

    public function load($entity, $rawInput)
    {
        // todo: this should never try setting the ID
        foreach ($rawInput as $propertyName => $value) {
            $propertyMetadata = new EntityPropertyMetadata(
                $this->em->getClassMetadata(get_class($entity)),
                $propertyName
            );

            // Skip properties that don't exist on the entity
            if (!$propertyMetadata->exists) continue;

            // Skip properties that the user cannot edit
            if (!$this->canEdit($entity, $propertyName)) continue;

            $resolvedValue = $this->resolveValue($value, $propertyMetadata);
            ZanObject::setProperty($entity, $propertyName, $resolvedValue);
        }
    }

    protected function resolveValue($rawValue, EntityPropertyMetadata $propertyMetadata)
    {
        // Return null without any modification
        if (null === $rawValue) return null;

        // Plain strings can be returned without modification
        if ('string' === $propertyMetadata->dataType) {
            return (string)$rawValue;
        }
        if ('text' === $propertyMetadata->dataType) {
            return (string)$rawValue;
        }
        if ('decimal' === $propertyMetadata->dataType) {
            return floatval($rawValue);
        }
        if ('integer' === $propertyMetadata->dataType) {
            return intval($rawValue);
        }
        if ('boolean' === $propertyMetadata->dataType) {
            return boolval($rawValue);
        }

        if ('entity' === $propertyMetadata->dataType) {
            return $this->resolveEntity($propertyMetadata->targetEntityClass, $rawValue);
        }

        throw new \InvalidArgumentException('No way to handle value for ' . $propertyMetadata->name . ' (' . $propertyMetadata->dataType . ')');
    }

    protected function resolveEntity($entityClassName, $id)
    {
        $entity = $this->em->find($entityClassName, $id);
        if (!$entity) {
            throw new \InvalidArgumentException(sprintf('Could not load %s by ID %s', $entityClassName, $id));
        }

        return $entity;
    }

    protected function canEdit($entity, $property)
    {
        // Deny by default
        if (!$this->permissionsCalculator) return false;

        return $this->permissionsCalculator->canEditEntityProperty($entity, $property, $this->actingUser);
    }

    public function getPermissionsCalculator(): ?PermissionsCalculatorInterface
    {
        return $this->permissionsCalculator;
    }

    public function setPermissionsCalculator(?PermissionsCalculatorInterface $permissionsCalculator): void
    {
        $this->permissionsCalculator = $permissionsCalculator;
    }
}