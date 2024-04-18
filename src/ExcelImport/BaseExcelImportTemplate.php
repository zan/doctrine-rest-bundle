<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Base class for all Excel import templates to inherit from
 */
abstract class BaseExcelImportTemplate
{
    protected EntityManagerInterface $em;

    private ?ExcelColumnCollection $columns = null;

    /**
     * Returns the default filename to save the Excel template as
     *
     * Choose a name that makes it easy for the user to find the right file to fill out
     */
    abstract public function getDownloadFilename(): string;

    /**
     * Builds and returns the ExcelColumn objects that will be used to manage the import
     */
    abstract protected function buildColumns(): ExcelColumnCollection;

    /**
     * @param mixed[] $rawValues
     */
    abstract public function processRow(ExcelImportContext $importContext, array $rawValues): void;

    /**
     * This method is responsible for generating a "simple" preview value for the given column and
     * an imported record
     *
     * This value should be suitable for display in a grid of data to the client and be a string, date, etc.
     * Avoid objects or other complicated nested data structures
     */
    abstract public function getPreviewValue(ExcelColumn $column, object $record): mixed;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function getTemplateFile(): string
    {
        $spreadsheet = new Spreadsheet();

        // Add columns
        $ws = $spreadsheet->getActiveSheet();

        $colIdx = 1; // 1-based Excel index
        foreach ($this->getColumns() as $column) {
            $cellCoord = [$colIdx, 1];

            /** @var ExcelColumn $column */
            $headerCell = $ws->getCell([$colIdx, 1]);

            $headerCell->setValue($column->getLabel());

            $headerStyle = [
                'font' => [ 'bold' => true ],
            ];
            $ws->getStyle($cellCoord)->applyFromArray($headerStyle);

            // Set a width, if specified
            if ($column->getWidthInChars()) {
                $ws->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))
                    ->setWidth($column->getWidthInChars());
            }
            // fall back to autosizing the column
            else {
                $ws->getColumnDimension(Coordinate::stringFromColumnIndex($colIdx))
                    ->setAutoSize(true);
            }

            // Freeze all rows above A2 (in other words, the header row)
            $ws->freezePane('A2');

            $colIdx++;
        }

        // Write file contents to memory and return the binary string
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save("php://output");

        $result = ob_get_clean();
        if ($result === false) throw new \ErrorException('Failed to write Excel file');

        return $result;
    }

    /**
     * Commits the import to the database and returns an array of objects representing the result of the import
     *
     * @return object[]
     */
    public function commitImport(ExcelImportContext $importContext): array
    {
        $result = $importContext->getImportResult();

        // Persist and flush imported entities
        foreach ($result as $record) {
            $this->em->persist($record);
        }
        $this->em->flush();

        return $result;
    }

    public function getColumns(): ExcelColumnCollection
    {
        if ($this->columns === null) {
            $this->columns = $this->buildColumns();
        }

        return $this->columns;
    }
}