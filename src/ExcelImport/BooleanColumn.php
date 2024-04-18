<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class BooleanColumn extends ExcelColumn
{
    /**
     * @param mixed[] $rawValues
     * @return ?bool
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        if ($this->cannotBeBlank() && !$value) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
            return null;
        }

        // Assume everything is truthy except for some explicit false values
        $falsyValues = ['n', 'no', 'false'];
        if (in_array($value, $falsyValues)) {
            $value = false;
        }

        return boolval($value);
    }
}