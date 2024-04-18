<?php

namespace Zan\DoctrineRestBundle\Util;

class StringUtils
{
    /**
     * Converts a camel-cased string to a spaced string and cap the first letter of each word.
     *
     * Examples:
     *      - MonthlyCharges => Monthly Charges
     *      - testString => Test String
     *      - TESTstring => Test String
     *
     * @return string
     */
    public static function camelCaseToTitleCase(?string $string): string
    {
        if (!$string) return '';

        /*
         * Insert a space before any capital letter followed by a lowercase letter
         *  - but not at the beginning of the string
         */
        $replaced = preg_replace('/(?<!^)([A-Z])(?=[a-z])/', ' $1', $string);

        // Special case: block of capitals at the end (exampleSTRING -> example STRING)
        $replaced = preg_replace('/^([a-z]+)([A-Z]+)$/', '$1 $2', $replaced);

        // Uppercase first letter of each word
        $replaced = ucwords(strtolower($replaced));

        return $replaced;
    }
}