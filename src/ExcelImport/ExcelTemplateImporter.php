<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use function App\ExcelImport\count;

class ExcelTemplateImporter
{
    public function import(BaseExcelImportTemplate $template, Worksheet $worksheet, ExcelImportContext $importContext): void
    {
        $columns = $template->getColumns();
        $columnKeys = $columns->getKeys();

        // Early exit if there isn't at least one data row
        if ($worksheet->getHighestRow() < 2) {
            return;
        }

        // Iterate 1-based rows starting at row 2 (skip header row)
        foreach ($worksheet->getRowIterator(2) as $row) {
            $cellIterator = $row->getCellIterator();
            // Iterate all cells regardless of whether they have values so we stay in sync with columns
            $cellIterator->setIterateOnlyExistingCells(false);

            $importContext->setCurrRowNumber($row->getRowIndex());

            $excelValues = [];
            $humanReadableValues = [];
            $colIdx = 0; // 0-based for working with the php columns array
            foreach ($cellIterator as $cell) {
                // Might have more known columns than data, ignore it here and let validation catch it
                // This is to make it easier to support multiple template versions that have optional columns added to them
                if ($colIdx > count($columnKeys) - 1) continue;

                $templateColumn = $columns->get($columnKeys[$colIdx]);
                if (!$templateColumn) throw new \LogicException('Failed to resolve column ' . $columnKeys[$colIdx]);

                $excelValues[$templateColumn->getId()] = $cell->getValue();
                $humanReadableValues[$templateColumn->getId()] = $templateColumn->getHumanReadableValue($cell);

                $colIdx++;
            }

            $importContext->appendRawValues($excelValues);
            $importContext->appendHumanReadableValues($humanReadableValues);

            // Send the raw row to the template for processing
            $template->processRow($importContext, $excelValues);
        }
    }
}