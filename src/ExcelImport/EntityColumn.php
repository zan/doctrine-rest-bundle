<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Doctrine\ORM\EntityManagerInterface;

class EntityColumn extends ExcelColumn
{
    /** @var class-string */
    private string $entityNamespace;

    /** @var string[] */
    private array $valueSearchFields = [];

    private bool $allowDeactivatedValues = true;

    protected EntityManagerInterface $em;

    /**
     * @param class-string $entityNamespace
     */
    public function __construct(string $id, string $label, string $entityNamespace, EntityManagerInterface $em)
    {
        parent::__construct($id, $label);

        $this->entityNamespace = $entityNamespace;
        $this->em = $em;
    }

    /**
     * @param mixed[] $rawValues
     * @return ?object
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        if ($this->cannotBeBlank() && !$value) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
            return null;
        }

        $mustBeActive = !$this->getAllowDeactivatedValues();

        $entity = $this->resolveEntity($value, $mustBeActive);

        // Didn't resolve an entity but there was something in the raw value, which means we should have
        if ($value && !$entity) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' does not contain a valid value');
        }

        return $entity;
    }

    protected function resolveEntity(mixed $rawValue, bool $mustBeActive = false): ?object
    {
        $repository = $this->em->getRepository($this->getEntityNamespace());

        // Search for entities by going through the value search fields and seeing if any match $rawValue
        $entity = null;
        foreach ($this->getValueSearchFields() as $valueField) {
            $criteria = [
                strval($valueField) => $rawValue,
            ];

            if ($mustBeActive) {
                $criteria['deactivatedAt'] = null;
            }

            $entity = $repository->findOneBy($criteria);

            if ($entity) break;
        }

        return $entity;
    }

    /**
     * @return class-string
     */
    public function getEntityNamespace(): string
    {
        return $this->entityNamespace;
    }

    /**
     * @param class-string $entityNamespace
     */
    public function setEntityNamespace(string $entityNamespace): void
    {
        $this->entityNamespace = $entityNamespace;
    }

    /**
     * @return string[]
     */
    public function getValueSearchFields(): array
    {
        return $this->valueSearchFields;
    }

    /**
     * @param string[] $valueSearchFields
     */
    public function setValueSearchFields(array $valueSearchFields): void
    {
        $this->valueSearchFields = $valueSearchFields;
    }

    public function getAllowDeactivatedValues(): bool
    {
        return $this->allowDeactivatedValues;
    }

    public function setAllowDeactivatedValues(bool $allowDeactivatedValues): void
    {
        $this->allowDeactivatedValues = $allowDeactivatedValues;
    }
}