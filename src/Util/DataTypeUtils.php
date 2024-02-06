<?php

namespace Zan\DoctrineRestBundle\Util;

class DataTypeUtils
{
    /**
     * Returns an integer if $value is either an integer or a string with characters 0-9
     *
     * This adds the following features over intval():
     *  - returns null on invalid values instead of 0
     *  - does not allow scientific notation
     *
     * @param mixed $value
     * @return int|null
     */
    public static function ensureInt(mixed $value)
    {
        // Early exit for nulls and ints since no conversion is needed
        if (is_null($value)) return null;
        if (is_int($value)) return $value;

        // Strings can be converted to ints
        if (is_string($value)) {
            // Only allow 0-9 (intval() allows scientific notation)
            if (!ctype_digit($value)) return null;

            return intval($value);
        }

        // Anything that can't be converted to an integer should be null as well
        return null;
    }
}