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
     * The namespace of the entity implementing GeneratedPublicIdInterface
     *
     * @ORM\Column(type="json", nullable=true)
     */
    private ?string $state = null;

    public function __construct(string $entityNamespace)
    {
        $this->entityNamespace = $entityNamespace;
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

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(?string $state): void
    {
        $this->state = $state;
    }
}