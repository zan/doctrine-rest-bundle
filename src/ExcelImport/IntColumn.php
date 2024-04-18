<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class IntColumn extends ExcelColumn
{
    /**
     * @param mixed[] $rawValues
     * @return ?int
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        // Treat an empty string as null for purposes of having a valid value
        if ('' === $value) $value = null;

        if ($this->cannotBeBlank() && $value === null) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
            return null;
        }

        return intval($value);
    }
}