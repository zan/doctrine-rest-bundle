<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class ExcelCellValidationProblem
{
    private bool $isError = false;

    private int $row;

    private string $columnId;

    private string $message;

    public function __construct(string $columnId, int $row, string $message, bool $isError = false)
    {
        $this->columnId = $columnId;
        $this->row = $row;
        $this->message = $message;
        $this->isError = $isError;
    }

    /**
     * @return array{
     *     'columnId': string,
     *     'row': int,
     *     'message': string,
     *     'isError': bool,
     * }
     */
    public function toArray(): array
    {
        return [
            'columnId' => $this->columnId,
            'row' => $this->row,
            'message' => $this->message,
            'isError' => $this->isError,
        ];
    }

    public function isError(): bool
    {
        return $this->isError;
    }

    public function setIsError(bool $isError): void
    {
        $this->isError = $isError;
    }

    public function getRow(): int
    {
        return $this->row;
    }

    public function setRow(int $row): void
    {
        $this->row = $row;
    }

    public function getColumnId(): string
    {
        return $this->columnId;
    }

    public function setColumnId(string $columnId): void
    {
        $this->columnId = $columnId;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}