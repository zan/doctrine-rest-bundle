<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class StringColumn extends ExcelColumn
{
    /**
     * @param mixed[] $rawValues
     * @return ?string
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        if ($this->cannotBeBlank() && !$value) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
        }

        return $value === null ? null : strval($value);
    }
}