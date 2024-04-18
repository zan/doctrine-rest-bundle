<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\BaseWriter;

class ImportableSpreadsheet extends Spreadsheet
{
    protected BaseExcelImportTemplate $importTemplate;

    protected Worksheet $worksheet;

    public function __construct(BaseExcelImportTemplate $importTemplate)
    {
        parent::__construct();

        $this->importTemplate = $importTemplate;

        $this->worksheet = $this->getSheet(0);

        // Builds the initial worksheet from data in the import template
        $this->populateWorksheet();
    }

    protected function populateWorksheet(): void
    {
        /** @var ExcelColumn[] $columns */
        $columns = $this->importTemplate->getColumns();

        // Write header row
        $colIdx = 1; // columns are 1-based in the spreadsheet
        foreach ($columns as $column) {
            $cell = $this->worksheet->getCell([$colIdx, 1]);

            $cell->setValue($column->getLabel());
            $cell->getStyle()->getFont()->setBold(true);

            $this->worksheet->getColumnDimension($cell->getColumn())->setAutoSize(true);

            $colIdx++;
        }
    }

    /**
     * Returns the raw excel file suitable for saving to disk or sending
     * to a browser.
     */
    public function getExcelContents(): string
    {
        /** @var BaseWriter $writer */
        $writer = IOFactory::createWriter($this, "Xlsx");
        ob_start();
        $writer->save("php://output");
        $writer->setIncludeCharts(true);

        return strval(ob_get_clean());
    }
}