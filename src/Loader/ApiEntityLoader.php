<?php


namespace Zan\DoctrineRestBundle\Loader;


use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use \Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Api\Error;
use Zan\DoctrineRestBundle\Exception\ApiException;
use Zan\DoctrineRestBundle\Loader\EntityPropertyMetadata;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorInterface;
use Doctrine\ORM\EntityManager;

/**
 * todo: permissionsCalculator is no longer necessary, remove it
 */
class ApiEntityLoader
{
    protected EntityManagerInterface $em;

    protected ?PermissionsCalculatorInterface $permissionsCalculator = null;

    protected ?PermissionsCalculatorFactory $permissionsCalculatorFactory = null;

    /**
     * If available, the logged in user for the current request
     */
    protected mixed $actingUser = null;

    /**
     * If available, the HTTP request
     */
    protected ?Request $request;

    protected bool $enableDebugging = false;

    public function __construct(
        EntityManagerInterface $em,
        ?RequestStack $requestStack = null,
        ?TokenStorageInterface $tokenStorage = null,
        ?PermissionsCalculatorFactory $permissionsCalculatorFactory = null,
    ) {
        $this->em = $em;
        if ($tokenStorage) {
            $this->actingUser = $tokenStorage->getToken()?->getUser();
        }
        $this->request = $requestStack?->getCurrentRequest();
        $this->permissionsCalculatorFactory = $permissionsCalculatorFactory;

        // Check if the client has requested additional debugging information
        // NOTE: this may be on in a production system
        if ($this->request && $this->request->query->get('zan_enableDebugging')) {
            $this->enableDebugging = true;
        }
    }

    public function setActingUser($actingUser)
    {
        $this->actingUser = $actingUser;
    }

    /**
     * Loads $entity from JSON data present in the current Request's body
     */
    public function loadFromRequest($entity)
    {
        if (!$this->request) throw new \LogicException('No HTTP request is available');

        $decodedBody = json_decode($this->request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->load($entity, $decodedBody);
    }

    /**
     * todo: docs and typehinting
     *
     * todo: document that this requires constructor arguments to be named in a certain way
     *
     * todo: permission checks should be added here similar to update() (they can then be removed from EntityDataController)
     */
    public function create($entityClassName, $rawInput)
    {
        $enableDebugging = $this->enableDebugging;

        if ($enableDebugging) {
            dump('Creating a new ' . $entityClassName . ' with');
            dump($rawInput);
        }

        // Inject constructor parameters if available
        $class = new \ReflectionClass($entityClassName);
        $constructor = $class->getConstructor();
        $constructorArgs = [];
        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                foreach ($rawInput as $key => $value) {
                    // Find parameters in the API call that match required arguments
                    if ($key != $parameter->getName()) continue;

                    $propertyMetadata = new EntityPropertyMetadata(
                        $this->em->getClassMetadata($entityClassName),
                        $key
                    );

                    $resolvedValue = $this->resolveValue($value, $propertyMetadata);
                    $constructorArgs[] = $resolvedValue;
                }
            }
        }

        if ($constructor && $constructor->getParameters() && $enableDebugging) {
            dump("With constructor arguments: ");
            dump($constructorArgs);
        }

        $reflectionClass = new \ReflectionClass($entityClassName);
        $newEntity = $reflectionClass->newInstanceArgs($constructorArgs);

        $this->load($newEntity, $rawInput);

        return $newEntity;
    }

    public function load($entity, $rawInput)
    {
        $enableDebugging = $this->enableDebugging;

        if ($enableDebugging) {
            dump('Loading ' . get_class($entity) . '#' . $entity->getId() . ' with');
            dump($rawInput);

            dump('Using permissions calculator: ' . get_class($this->permissionsCalculatorFactory->getPermissionsCalculator($entity)));
        }

        // todo: this might need additional checking to guarantee the entityVersion field is always editable if other fields are?

        foreach ($rawInput as $propertyName => $value) {
            // Never attempt to overwrite the ID
            if ('id' === $propertyName) continue;

            $propertyMetadata = new EntityPropertyMetadata(
                $this->em->getClassMetadata(get_class($entity)),
                $propertyName
            );

            // Skip properties that don't exist on the entity
            if (!$propertyMetadata->exists) {
                if ($enableDebugging) dump("Not setting ${propertyName}: this property does not exist on " . get_class($entity));
                continue;
            }

            // Skip properties that the user cannot edit
            if (!$this->canEdit($entity, $propertyName)) {
                if ($enableDebugging) dump("Not setting ${propertyName}: user does not have permissions to edit this field on a " . get_class($entity));
                continue;
            }

            if ($enableDebugging) dump("Setting " . get_class($entity) . "::${propertyName} to:");
            $resolvedValue = $this->resolveValue($value, $propertyMetadata);

            if ($enableDebugging) dump($resolvedValue);
            $success = ZanObject::setProperty($entity, $propertyName, $resolvedValue);
            if (!$success) {
                throw new ApiException(
                    sprintf(
                        'Could not set "%s" on "%s" because it does not have a setter',
                        $propertyName,
                        get_class($entity),
                    ),
                    Error::NO_ENTITY_PROPERTY_SETTER,
                );
            }
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

        // Dates
        if ('datetime_immutable' === $propertyMetadata->dataType) {
            return \DateTimeImmutable::createFromFormat(DATE_ATOM, $rawValue);
        }

        if ('entity' === $propertyMetadata->dataType) {
            // Multi-value association and $rawValue is an array, eg. OneToMany or ManyToMany
            if ($propertyMetadata->isToManyAssociation()) {
                $resolvedEntities = [];
                // Interpret an emptyish $rawValue as an empty array
                if (!$rawValue) $rawValue = [];
                foreach ($rawValue as $entityIdOrPropertyValues) {
                    $entityId = null;
                    // Check for a complex array representing properties
                    if (is_array($entityIdOrPropertyValues)) {
                        // Numeric array, assume this is a flat array of IDs
                        if (array_key_exists(0, $entityIdOrPropertyValues)) {
                            $entityId = $entityIdOrPropertyValues;
                        }
                        else {
                            // It might be an existing record where we're getting all the fields
                            if (array_key_exists('id', $entityIdOrPropertyValues)) {
                                $entityId = $entityIdOrPropertyValues['id'];
                            }
                        }
                    }
                    // Otherwise, it's a simple integer array
                    else {
                        $entityId = $entityIdOrPropertyValues;
                    }

                    if ($entityId) {
                        $resolvedEntities[] = $this->resolveEntity($propertyMetadata->targetEntityClass, $entityId);
                    }
                    // If we get to this point with no entity ID then it's probably an array of fields representing a new entity
                    else {
                        $newEntity = $this->create($propertyMetadata->targetEntityClass, $entityIdOrPropertyValues);
                        $resolvedEntities[] = $newEntity;
                    }
                }

                return $resolvedEntities;
            }
            // A single entity, eg. ManyToOne or OneToOne
            else {
                // We're loading into a single-value association but an "object" was passed, assume it represents a
                // serialized object and extract the ID
                if (is_array($rawValue)) {
                    return $this->resolveEntity($propertyMetadata->targetEntityClass, $rawValue['id']);
                }
                // Scalar value representing an ID
                else {
                    return $this->resolveEntity($propertyMetadata->targetEntityClass, $rawValue);
                }
            }
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
        // Deny if there's no permissions calculator available
        if (!$this->permissionsCalculatorFactory) return false;

        return $this->permissionsCalculatorFactory
            ->getPermissionsCalculator($entity)
            ->canEditEntityProperty($entity, $property, $this->actingUser);
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