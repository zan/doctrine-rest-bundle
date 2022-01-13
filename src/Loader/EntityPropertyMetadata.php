<?php


namespace Zan\DoctrineRestBundle\Loader;


use Doctrine\ORM\Mapping\ClassMetadata;

class EntityPropertyMetadata
{
    /** @var bool True if the property was found on the entity */
    public $exists = false;

    /** @var string */
    public $name;

    /** @var bool Whether this is a field (as opposed to an association) */
    public $isField;

    /** @var string */
    public $targetEntityClass;

    /** @var array field or association metadata as read from the ClassMetadata */
    public $doctrineMetadata;

    /**
     * For fields, this is the data type as reported by doctrine
     *
     * todo: associations / arrays
     * @var string
     */
    public $dataType;

    public function __construct(ClassMetadata $entityMetadata, $property)
    {
        $this->name = $property;

        // Check direct entity properties
        if (array_key_exists($property, $entityMetadata->fieldMappings)) {
            $this->exists = true;
            $this->doctrineMetadata = $entityMetadata->fieldMappings[$property];
            $this->dataType = $this->doctrineMetadata['type'];
        }

        // Check associations
        if (array_key_exists($property, $entityMetadata->associationMappings)) {
            $this->exists = true;
            $this->doctrineMetadata = $entityMetadata->associationMappings[$property];
            $this->dataType = 'entity';
            $this->targetEntityClass = $this->doctrineMetadata['targetEntity'];
        }
    }

    /**
     * Returns true if this property is a "to many" association such as "one to many" or "many to many"
     */
    public function isToManyAssociation()
    {
        if (ClassMetadata::ONE_TO_MANY === $this->doctrineMetadata['type']) return true;
        if (ClassMetadata::MANY_TO_MANY === $this->doctrineMetadata['type']) return true;

        return false;
    }
}