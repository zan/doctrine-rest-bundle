<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class FloatColumn extends ExcelColumn
{
    /**
     * @param mixed[] $rawValues
     * @return ?float
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        // Treat an empty string as null for purposes of having a valid float value
        if ('' === $value) $value = null;

        if ($this->cannotBeBlank() && $value === null) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
            return null;
        }

        return floatval($value);
    }
}