<?php

namespace Zan\DoctrineRestBundle\Controller;

use App\Controller\BaseAppController;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Zan\DoctrineRestBundle\EntitySerializer\MinimalEntitySerializer;
use Zan\DoctrineRestBundle\ExcelImport\BaseExcelImportTemplate;
use Zan\DoctrineRestBundle\ExcelImport\ExcelColumn;
use Zan\DoctrineRestBundle\ExcelImport\ExcelColumnCollection;
use Zan\DoctrineRestBundle\ExcelImport\ExcelImportContext;
use Zan\DoctrineRestBundle\ExcelImport\ExcelProblemCollection;
use Zan\DoctrineRestBundle\ExcelImport\ExcelTemplateImporter;
use Zan\DoctrineRestBundle\Response\FileDownloadResponse;
use Zan\DoctrineRestBundle\Util\DoctrineRestUtils;

/**
 * Manages the process of uploading and importing data from Excel files
 *
 * NOTE: This functionality requires phpoffice/phpspreadsheet to be installed
 */
#[Route("/excel-import")]
class ExcelImportController extends BaseAppController
{
    /**
     * Downloads the empty Excel template for the user to fill out
     */
    #[Route(path: "/{templateIdentifier}", methods: ["GET"])]
    public function downloadTemplate(
        string $templateIdentifier,
        ContainerInterface $container,
    ): Response {
        $templateServiceId = DoctrineRestUtils::unescapeEntityId($templateIdentifier);

        /** @var ?BaseExcelImportTemplate $template */
        $template = $container->get($templateServiceId);
        if (!$template) throw new \InvalidArgumentException('Template not found, ensure it is registered as a service');

        return new FileDownloadResponse(
            $template->getDownloadFilename(),
            $template->getTemplateFile(),
            null,
            true
        );
    }

    /**
     * Processes the uploaded file to generate a preview of what will be imported
     */
    #[Route(path: "/{templateIdentifier}/preview", methods: ["PUT"])]
    public function preview(
        string                $templateIdentifier,
        ContainerInterface    $container,
        Request               $request,
        ExcelTemplateImporter $excelImporter,
    ): JsonResponse {
        $importContext = new ExcelImportContext();
        $importContext->setRawRequest($request);

        $template = $this->resolveTemplate($templateIdentifier, $container);

        // Read the first worksheet from the uploaded file
        $worksheet = $this->resolveUploadedWorksheet($request);

        $excelImporter->import($template, $worksheet, $importContext);

        $responseData = [
            'success' => true,
            'problems' => $this->serializeProblems($importContext->getProblems()),
            'columns' => $this->serializeColumns($template->getColumns()),
        ];

        // If there aren't any errors then we can generate the import preview
        if (!$importContext->hasErrors()) {
            $responseData['previewData'] = $this->serializePreviewData($importContext, $template);
        }

        // If there were problems, include the raw excel data so it can be displayed to the user
        // This is not included by default since it can be quite large
        if (!$importContext->getProblems()->isEmpty()) {
            $responseData['rawExcelData'] = $importContext->getHumanReadableValues();
        }

        return new JsonResponse($responseData);
    }

    /**
     * Performs the import and commits the data to the database
     */
    #[Route(path: "/{templateIdentifier}/import", methods: ["POST"])]
    public function import(
        string                  $templateIdentifier,
        ContainerInterface      $container,
        Request                 $request,
        MinimalEntitySerializer $serializer,
        ExcelTemplateImporter   $excelImporter,
    ): JsonResponse {
        $importContext = new ExcelImportContext();
        $importContext->setRawRequest($request);

        $template = $this->resolveTemplate($templateIdentifier, $container);

        // Read the first worksheet from the uploaded file
        $worksheet = $this->resolveUploadedWorksheet($request);

        $excelImporter->import($template, $worksheet, $importContext);

        $result = $template->commitImport($importContext);

        // Serialize the result of the import
        $responseFields = $serializer->getSerializationMapFromRequest($request);

        $responseData = [
            'success' => true,
            'data' => $serializer->serialize($result, $responseFields),
            'columns' => $this->serializeColumns($template->getColumns()),
        ];

        return new JsonResponse($responseData);
    }

    protected function resolveTemplate(string $templateIdentifier, ContainerInterface $container): BaseExcelImportTemplate
    {
        $templateServiceId = DoctrineRestUtils::unescapeEntityId($templateIdentifier);

        /** @var ?BaseExcelImportTemplate $template */
        $template = $container->get($templateServiceId);
        if (!$template) throw new \InvalidArgumentException("Template not found, ensure '${templateIdentifier}' is registered as a service");

        return $template;
    }

    protected function resolveUploadedWorksheet(Request $request): Worksheet
    {
        $uploadedFile = null;
        foreach ($request->files->all() as $files) {
            foreach ($files as $fileData) {
                /** @var UploadedFile $uploadedFile */
                $uploadedFile = $fileData['file'];
                break;
            }
        }
        if (!$uploadedFile) throw new \InvalidArgumentException('No file was uploaded');

        return $this->readFirstWorksheet($uploadedFile->getRealPath());
    }

    /**
     * @return mixed[] @see ExcelCellValidationProblem for array details
     */
    protected function serializeProblems(ExcelProblemCollection $problems): array
    {
        $serialized = [];

        foreach ($problems as $problem) {
            $serialized[] = $problem->toArray();
        }

        return $serialized;
    }

    /**
     * @return array<int,array{ 'id': string, 'label': string }>
     */
    protected function serializeColumns(ExcelColumnCollection $columns): array
    {
        $serialized = [];

        /** @var ExcelColumn $column */
        foreach ($columns as $column) {
            $serialized[] = [
                'id' => $column->getId(),
                'label' => $column->getLabel(),
            ];
        }

        return $serialized;
    }

    /**
     * Serializes data so that it can be displayed as a preview
     *
     * In general, this means getting scalar values instead of complex nested objects / arrays / etc.
     *
     * @return mixed[]
     */
    protected function serializePreviewData(ExcelImportContext $importContext, BaseExcelImportTemplate $template): array
    {
        $previewRows = [];

        foreach ($importContext->getImportResult() as $record) {
            $previewRow = [];

            /** @var ExcelColumn $column */
            foreach ($template->getColumns() as $column) {
                $previewRow[$column->getId()] = $template->getPreviewValue($column, $record);
            }

            $previewRows[] = $previewRow;
        }

        return $previewRows;
    }

    protected function readFirstWorksheet(string $filePath): Worksheet
    {
        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($filePath);

        return $spreadsheet->getSheet(0);
    }
}