<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Symfony\Component\HttpFoundation\Request;

class ExcelImportContext
{
    private ?int $currRowNumber = null;


    /** @var Request If available, the request used when uploading the excel file */
    private Request $rawRequest;

    /**
     * A two-dimensional array containing raw values read from the Excel file
     *
     * IMPORTANT:
     *  - This array is built while processing the Excel file, it may not be complete
     *  - This is a 0-based PHP array and not a 1-based Excel array
     *
     * @var mixed[]
     */
    private array $rawValues = [];

    /**
     * An array of objects where the keys are column IDs and the value is a string representing
     * a human-readable view of the data.
     *
     * For example, dates show up as an integer number of days in rawValues but a string like "2024-04-17 12:00" here
     *
     * This is used when problems are found in the Excel file
     *
     * @var string[]
     */
    private array $humanReadableValues = [];

    private ExcelProblemCollection $problems;

    /** @var object[] */
    private array $importResult = [];

    public function __construct()
    {
        $this->problems = new ExcelProblemCollection();
    }

    public function addErrorToCurrentRow(string $columnId, string $message): ExcelCellValidationProblem
    {
        if (null === $this->getCurrRowNumber()) throw new \LogicException('Import has no current row number');

        $problem = new ExcelCellValidationProblem($columnId, $this->getCurrRowNumber(), $message, true);

        $this->problems->add($problem);

        return $problem;
    }

    public function currentRowHasErrors(): bool
    {
        // Early exit if there's no current row
        if (null === $this->getCurrRowNumber()) return false;

        foreach ($this->problems as $problem) {
            /** @var $problem ExcelCellValidationProblem */
            if ($problem->getRow() === $this->getCurrRowNumber()) return true;
        }

        return false;
    }

    /**
     * An array of objects generated from the data in the Excel file
     *
     * @return object[]
     */
    public function getImportResult(): array
    {
        return $this->importResult;
    }

    public function addImportResult(object $item): void
    {
        $this->importResult[] = $item;
    }

    public function getCurrRowNumber(): ?int
    {
        return $this->currRowNumber;
    }

    public function setCurrRowNumber(?int $currRowNumber): void
    {
        $this->currRowNumber = $currRowNumber;
    }

    public function hasErrors(): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->isError()) return true;
        }

        return false;
    }

    public function getProblems(): ExcelProblemCollection
    {
        return $this->problems;
    }

    public function setProblems(ExcelProblemCollection $problems): void
    {
        $this->problems = $problems;
    }

    /**
     * @return mixed[]
     */
    public function getRawValues(): array
    {
        return $this->rawValues;
    }

    /**
     * @return string[]
     */
    public function getHumanReadableValues(): array
    {
        return $this->humanReadableValues;
    }

    /**
     * Appends the raw values for a given row to the array of raw values
     *
     * @param mixed[] $row
     */
    public function appendRawValues(array $row): void
    {
        $this->rawValues[] = $row;
    }

    /**
     * Appends the preview values for a given row to the array of preview values
     *
     * @param string[] $row
     */
    public function appendHumanReadableValues(array $row): void
    {
        $this->humanReadableValues[] = $row;
    }

    public function getRawRequest(): Request
    {
        return $this->rawRequest;
    }

    public function setRawRequest(Request $rawRequest): void
    {
        $this->rawRequest = $rawRequest;
    }
}