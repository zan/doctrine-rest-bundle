<?php


namespace Zan\DoctrineRestBundle\EntitySerializer;


use Zan\CommonBundle\Util\ZanObject;
use Zan\CommonBundle\Util\ZanString;
use Zan\DoctrineRestBundle\EntitySerializer\FieldMap;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

class MinimalEntitySerializer
{
    /** @var EntityManager */
    protected $em;

    /** @var Reader */
    protected $annotationReader;

    protected static $propertyAvailableForSerializationCache = [];

    public function __construct(EntityManagerInterface $em, Reader $annotationReader)
    {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
    }

    public function serialize($entity, array $serializationMap = []): array
    {
        $fieldMap = new FieldMap($serializationMap);
        $metadata = $this->em->getClassMetadata(get_class($entity));

        $serialized = $this->recursiveSerialize($entity, $fieldMap, '', $metadata);

        return $serialized;
    }

    protected function recursiveSerialize($entity, FieldMap $fieldMap, $parentPath, ClassMetadata $metadata)
    {
        $serialized = [];

        // Serialize properties first
        foreach ($metadata->fieldNames as $columnName => $propertyName) {
            // If recursing, ignore fields that don't match the map
            if ($parentPath && !$fieldMap->contains($parentPath . $propertyName)) continue;

            // Ignore properties that aren't exposed to the serializer
            if (!$this->propertyAvailableForSerialization($entity, $propertyName)) continue;

            $value = $this->getSerializedPropertyValue($entity, $propertyName);

            // Do not send null values
            if (null === $value) continue;

            $serialized[$propertyName] = $value;
        }

        // Add relationships
        foreach ($metadata->associationMappings as $propertyName => $association) {
            $associationMetadata = $this->em->getClassMetadata($association['targetEntity']);

            // Ignore properties that aren't exposed to the serializer
            if (!$this->propertyAvailableForSerialization($entity, $propertyName)) continue;

            // "type" is a bitmask, see doctrine's ClassMetadaInfo.php
            // Convert "To Many" associations to an array
            if (isset($association['type']) && $association['type'] & ClassMetadataInfo::TO_MANY) {
                $checkPath = $parentPath . $propertyName;
                if ($fieldMap->contains($checkPath)) {
                    $assocValues = ZanObject::getPropertyValue($entity, $propertyName, true);
                    $serValues = [];
                    foreach ($assocValues as $assocValue) {
                        $serValues[] = $this->recursiveSerialize($assocValue, $fieldMap, $parentPath . $propertyName . '.', $associationMetadata);
                    }
                    $serialized[$propertyName] = $serValues;
                }
                continue; // no more processing since this association is handled
            }

            // Entities with composite identifiers aren't supported
            if ($associationMetadata->isIdentifierComposite) continue;
            // Entities without identifiers aren't supported
            if (count($associationMetadata->identifier) === 0) continue;
            $identifierProperty = $associationMetadata->identifier[0];

            $associationEntity = ZanObject::getPropertyValue($entity, $propertyName, true);
            if (null === $associationEntity) continue;

            // If this association isn't in the list of serialized fields, return the identifier of the related entity
            $checkPath = $parentPath . $propertyName . '.' . $identifierProperty;
            if (!$fieldMap->contains($checkPath)) {
                // todo: should support alternate primary keys
                if (is_callable([$associationEntity, 'getId'])) {
                    $serialized[$propertyName] = $associationEntity->getId();
                }
                // todo: arrays of entities? not sure about this, I think it would always require an additional join
                // skip associations that aren't to things with a primary key
                continue;
            }
            else {
                $serialized[$propertyName] = $this->recursiveSerialize($associationEntity, $fieldMap, $parentPath . $propertyName . '.', $associationMetadata);
            }
        }

        // virtual properties such as methods that expose data
        foreach (ZanObject::getMethods($entity) as $method) {
            // todo: support customizing this
            $propertyName = lcfirst(ZanString::removePrefix($method->getName(), 'get'));

            // todo: need to check if this is in the field map, should not include by default
            $checkPath = $parentPath . $propertyName;
            //dump("Checking for: ", $checkPath);
            if (!$fieldMap->contains($checkPath)) continue;

            if (!$this->isMethodExposed($method)) continue;


            $value = $this->getSerializedMethodValue($entity, $method);

            $serialized[$propertyName] = $value;
        }

        return $serialized;
    }

    protected function isMethodExposed(\ReflectionMethod $method): bool
    {
        // Only consider public methods
        if (!$method->isPublic()) return false;

        $annotations = $this->annotationReader->getMethodAnnotations($method);

        foreach ($annotations as $annotation) {
            $className = get_class($annotation);

            if ('Zan\DoctrineRestBundle\Annotation\ApiEnabled' === $className) return true;
        }

        return false;
    }

    protected function getSerializedMethodValue($entity, \ReflectionMethod $method)
    {
        $value = $method->invoke($entity);

        return $this->getSerializedValue($value);
    }

    protected function getSerializedPropertyValue($entity, $property)
    {
        $value = ZanObject::getPropertyValue($entity, $property, true);

        return $this->getSerializedValue($value);
    }

    protected function getSerializedValue($rawValue)
    {
        if ($rawValue instanceof \DateTimeInterface) {
            return $rawValue->format(DATE_ATOM);
        }

        return $rawValue;
    }

    protected function propertyAvailableForSerialization($entity, $property): bool
    {
        $cacheKey = sprintf('%s::%s', get_class($entity), $property);

        if (array_key_exists($cacheKey, static::$propertyAvailableForSerializationCache)) {
            return static::$propertyAvailableForSerializationCache[$cacheKey];
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

            // JMS Serializer "Expose"
            if ('JMS\Serializer\Annotation\Expose' === $className) {
                $isAvailable = true;
                break;
            }

            // Symfony serializer "Ignore"
            if ('Symfony\Component\Serializer\Annotation\Ignore' === $className) {
                $isAvailable = false;
            }
        }

        static::$propertyAvailableForSerializationCache[$cacheKey] = $isAvailable;
        return $isAvailable;
    }
}