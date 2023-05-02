<?php

namespace Zan\DoctrineRestBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Stores state used for generating public IDs on entities that implement GeneratedPublicIdInterface
 * 
 * @ORM\Entity
 * @ORM\Table(name="zan_drest_generatedPublicIdState")
 */
class GeneratedPublicIdStateEntry
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id;

    /**
     * The namespace of the entity implementing GeneratedPublicIdInterface
     *
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private string $entityNamespace;

    /**
     * A unique identifier for the entity that is associated with this state entry
     *
     * If there will only ever be one instance of the entity implementing GeneratedPublicIdInterface
     * then this can be the same as the namespace of the entity.
     *
     * If there will be multiple instances, build a unique key using the namespace and
     * entity ID (for example).
     *
     * @ORM\Column(type="string", length=255, nullable=false, unique=true)
     */
    private string $entityKey;

    /**
     * The namespace of the entity implementing GeneratedPublicIdInterface
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private mixed $state = null;

    public function __construct(string $entityNamespace, string $entityKey)
    {
        $this->entityNamespace = $entityNamespace;
        $this->entityKey = $entityKey;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    public function setEntityNamespace(string $entityNamespace): void
    {
        $this->entityNamespace = $entityNamespace;
    }

    public function getState(): mixed
    {
        return $this->state;
    }

    public function setState(mixed $state): void
    {
        $this->state = $state;
    }

    public function getEntityKey(): string
    {
        return $this->entityKey;
    }

    public function setEntityKey(string $entityKey): void
    {
        $this->entityKey = $entityKey;
    }
}