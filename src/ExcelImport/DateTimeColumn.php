<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class DateTimeColumn extends ExcelColumn
{
    public function getHumanReadableValue(Cell $cell): string
    {
        $rawValue = $cell->getValue();
        if (!$rawValue) return '';

        $asDate = Date::excelToDateTimeObject(floatval($cell->getValue()));

        return $asDate->format('Y-m-d H:i');
    }

    /**
     * @param mixed[] $rawValues
     * @return ?\DateTimeImmutable
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

        $floatVal = floatval($rawValues[$this->getId()]);
        return \DateTimeImmutable::createFromMutable(Date::excelToDateTimeObject($floatVal));
    }
}