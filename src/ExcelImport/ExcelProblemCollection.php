<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int|string, ExcelCellValidationProblem>
 */
class ExcelProblemCollection extends ArrayCollection
{
    public function addError(string $message, string $columnId, int $row): void
    {
        $this->add(new ExcelCellValidationProblem($columnId, $row, $message, true));
    }

    public function addWarning(string $columnId, int $row, string $message): void
    {
        $this->add(new ExcelCellValidationProblem($columnId, $row, $message, false));
    }
}