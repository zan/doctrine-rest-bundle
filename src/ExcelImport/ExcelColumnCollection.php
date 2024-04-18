<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Doctrine\Common\Collections\ArrayCollection;

/**
 * @extends ArrayCollection<int|string, ExcelColumn>
 */
class ExcelColumnCollection extends ArrayCollection
{
    /**
     * Convenience function to call the column's readValue() method
     *
     * @param mixed[] $rawValues
     */
    public function readValue(string $columnId, ExcelImportContext $importContext, array $rawValues): mixed
    {
        return $this->get($columnId)?->readValue($importContext, $rawValues);
    }

    /**
     * OVERRIDDEN for typehinting
     *
     * @return ?ExcelColumn
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * @param ExcelColumn $element
     */
    public function add($element)
    {
        $id = $element->getId();
        if ($this->containsKey($id)) throw new \InvalidArgumentException("Column with ID $id has already been added");

        $this->set($id, $element);

        return true;
    }

    public function addNewColumn(string $id, string $label): ExcelColumn
    {
        if ($this->containsKey($id)) throw new \InvalidArgumentException("Column with ID $id has already been added");

        $column = new ExcelColumn($id, $label);

        $this->set($id, $column);

        return $column;
    }
}