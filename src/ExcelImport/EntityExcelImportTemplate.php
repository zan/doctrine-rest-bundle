<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

use Zan\CommonBundle\Util\ZanObject;
use Zan\DoctrineRestBundle\Attribute\HumanReadableLabel;
use Zan\DoctrineRestBundle\Loader\EntityPropertyMetadata;
use Zan\DoctrineRestBundle\Util\StringUtils;

/**
 * Parent class for implementing an entity-based Excel import where a spreadsheet
 * generates one entity per row
 *
 * See abstract methods for implementation details
 */
abstract class EntityExcelImportTemplate extends BaseExcelImportTemplate
{
    /**
     * Returns the fully-qualified class name of the entity that should be created
     * from each row
     *
     * For example: return HistologySpecimen::class;
     */
    abstract function getEntityClassName(): string;

    /**
     * Returns an array of properties that should be available in the template and
     * written to the new entity
     *
     * For example: return ['label', 'sourceTissue'];
     */
    abstract function getImportableProperties(): array;

    /**
     * Given raw values and data in the ExcelImportContext this method is responsible
     * for creating a new instance of the entity being imported
     *
     * For example: return new HistologySpecimen($request);
     */
    abstract function createNewEntity(array $rawValues, ExcelImportContext $importContext): object;

    public function buildColumns(): ExcelColumnCollection
    {
        $columns = new ExcelColumnCollection();

        foreach ($this->getImportableProperties() as $property) {
            $importColumn = $this->buildEntityColumn($property);
            $columns->add($importColumn);
        }

        return $columns;
    }

    public function processRow(ExcelImportContext $importContext, array $rawValues): void
    {
        $columns = $this->buildColumns();

        // Values that have been converted from their raw value
        // the key is the column ID, value is the converted value
        $resolvedValues = [];

        // Read the value for each property
        foreach ($this->getImportableProperties() as $property) {
            $importColumn = $columns->get($property);
            $resolvedValues[$property] = $importColumn->readValue($importContext, $rawValues);
        }

        // Stop processing if there are any validation errors with this row
        if ($importContext->currentRowHasErrors()) {
            return;
        }

        // If everything looks good, create a new instance of the entity
        $entity = $this->createNewEntity($rawValues, $importContext);

        // Set properties in the entity
        foreach ($resolvedValues as $property => $value) {
            // Do not set null values
            if ($value === null) continue;

            ZanObject::setProperty($entity, $property, $value);
        }

        $importContext->addImportResult($entity);
    }

    public function getPreviewValue(ExcelColumn $column, object $record): mixed
    {
        $rawValue = ZanObject::getPropertyValue($record, $column->getId());

        // Preview value must always be a string since it's rendered in a grid cell or other simple display
        return ExcelImportUtils::toPreviewString($rawValue);
    }

    /**
     * Returns the correct ExcelColumn based on the data type and attributes of $property
     */
    protected function buildEntityColumn(string $property): ExcelColumn
    {
        $label = $property;

        // Check if there's a HumanReadableLabel attribute
        $reflProperty = ZanObject::getProperty($this->getEntityClassName(), $property);
        $attributes = $reflProperty->getAttributes(HumanReadableLabel::class);
        if (count($attributes) > 0) {
            $label = $attributes[0]->newInstance()->getLabel();
        }
        // Fall back to converting camel case to title case
        else {
            $label = StringUtils::camelCaseToTitleCase($property);
        }

        $propertyMetadata = new EntityPropertyMetadata(
            $this->em->getClassMetadata($this->getEntityClassName()),
            $property
        );

        // Set depending on the configuration of the entity property
        $column = null;

        if ('entity' === $propertyMetadata->dataType) {
            // Multi-value associations, eg. OneToMany or ManyToMany
            if ($propertyMetadata->isToManyAssociation()) {
                $column = new MultiEntityColumn($property, $label, $propertyMetadata->targetEntityClass, $this->em);
            }
            // A single entity, eg. ManyToOne or OneToOne
            else {
                $column = new EntityColumn($property, $label, $propertyMetadata->targetEntityClass, $this->em);

                // Make this field required if it's not nullable
                $doctrineMetadata = $propertyMetadata->doctrineMetadata;
                if ($doctrineMetadata['isOwningSide'] && $doctrineMetadata['joinColumns'][0]['nullable'] === false) {
                    $column->setAllowBlank(false);
                }
            }

            // Common properties for entity-based columns
            $column->setValueSearchFields(['label']);
            $column->setAllowDeactivatedValues(false);
        }
        else {
            $column = new StringColumn($property, $label);
        }

        return $column;
    }
}