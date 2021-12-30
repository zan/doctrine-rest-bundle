<?php

namespace Zan\DoctrineRestBundle\Util;

class DoctrineRestUtils
{
    /**
     * Un-escapes a dot-separated entity path and replaces them with PHP namespace separators
     */
    public static function unescapeEntityId($escapedEntityId)
    {
        return str_replace('.', '\\', $escapedEntityId);
    }
}