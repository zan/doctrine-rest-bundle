<?php

namespace Zan\DoctrineRestBundle\ExcelImport;

class ExcelImportUtils
{
    public static function toPreviewString(mixed $raw) {
        // Recurse into things like arrays and ArrayCollections
        if (is_iterable($raw)) {
            $stringVals = [];
            foreach ($raw as $value) {
                $stringVals[] = static::toPreviewString($value);
            }

            return join(", ", $stringVals);
        }
        elseif ($raw === null)
        {
            return '';
        }
        elseif ($raw instanceof \DateTimeInterface)
        {
            return $raw->format('Y-m-d H:i');
        }
        // For everything else, cast to a string
        else {
            return strval($raw);
        }
    }
}