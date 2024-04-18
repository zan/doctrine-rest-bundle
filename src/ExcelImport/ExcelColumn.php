<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use PhpOffice\PhpSpreadsheet\Cell\Cell;

abstract class ExcelColumn
{
    private string $id;

    private string $label;

    private bool $allowBlank = true;

    /**
     * @var int|null If set, the width of the column (in characters) in the Excel template
     *
     * For example, a width of 20 means that the column can show 20 visible characters in the default font
     */
    private ?int $widthInChars = null;

    public function __construct(string $id, string $label)
    {
        $this->id = $id;
        $this->label = $label;
    }

    public function getHumanReadableValue(Cell $cell): string
    {
        return strval($cell->getFormattedValue());
    }

    /**
     * @param mixed[] $rawValues
     */
    public function readValue(ExcelImportContext $context, array $rawValues): mixed
    {
        $value = $rawValues[$this->getId()] ?? null;

        if ($this->cannotBeBlank() && !$value) {
            $context->addErrorToCurrentRow($this->getId(), $this->getLabel() . ' cannot be blank');
        }

        return $value === null ? null : $value;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function canBeBlank(): bool
    {
        return $this->allowBlank;
    }

    public function cannotBeBlank(): bool
    {
        return !$this->allowBlank;
    }

    public function setAllowBlank(bool $allowBlank): void
    {
        $this->allowBlank = $allowBlank;
    }

    public function getWidthInChars(): ?int
    {
        return $this->widthInChars;
    }

    public function setWidthInChars(?int $widthInChars): void
    {
        $this->widthInChars = $widthInChars;
    }
}