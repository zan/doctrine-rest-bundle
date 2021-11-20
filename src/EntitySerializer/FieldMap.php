<?php


namespace Zan\DoctrineRestBundle\EntitySerializer;


use Zan\CommonBundle\Util\ZanString;

/**
 * todo: still necessary? most of this can be deleted?
 */
class FieldMap
{
    /** @var array  */
    protected $map;

    /**
     * @var array If present, root fields to be serialized
     *
     * By default, all root fields are serialized. Specifying root fields means only fields explicitly set
     * will get serialized
     */
    protected $rootFields = [];

    public function __construct(array $map)
    {
        $this->map = $this->parseMap($map);
    }

    public function contains($propertyPath): bool
    {
        // Trim trailing "."
        $propertyPath = rtrim($propertyPath, '.');
        return isset($this->map[$propertyPath]);

        $parsedPath = explode('.', $propertyPath);

        // Special case if there aren't any dots, this is a root field
        if (count($parsedPath) === 1) {
            return true;
            // Allow all root fields if none are explicitly set
            if (count($this->rootFields) === 0) return true;
            return array_key_exists($propertyPath, $this->rootFields);
        }

        $property = array_pop($parsedPath);
        $key = join('.', $parsedPath); // $parsedPath is now the parent path

        // Never true if we don't have a key
        if (!array_key_exists($key, $this->map)) return false;

        foreach ($this->map[$key] as $item) {
            dump("Comparing " . $item . " <> " . $property);
            if ($item === $property) return true;

            // '*' matches all items
            if (fnmatch($item, $property)) return true;
        }

        return false;
    }

    /**
     * Converts the flat array specifying which fields to include into a map of parent keys to child properties
     *
     * For example:
     *  requester.*
     *  department.head.id
     *  department.head.name
     *
     * Parses to:
     *  [
     *      'requester' => ['*'],
     *      'department.head' => ['id', 'name'],
     *  ]
     */
    protected function parseMap($map)
    {
        // Convert list of fields to be indexed by path for faster lookups
        return array_flip($map);

        return;
        $parsedMap = [];

        $maxDepth = 0;
        foreach ($map as $item) {
            $itemDepth = substr_count($item, '.');
            if ($itemDepth > $maxDepth) $maxDepth = $itemDepth;

            // Check for depth 0 fields and add them to the root fields array
            if ($itemDepth === 0) {
                $this->rootFields[$item] = true;
            }
        }

        for ($currDepth=1; $currDepth <= $maxDepth; $currDepth++) {
            foreach ($map as $item) {
                $itemDepth = substr_count($item, '.');
                if ($itemDepth !== $currDepth) continue;

                $parsed = explode('.', $item);
                $property = $parsed[count($parsed) - 1];
                $key = substr($item, 0, 0 - strlen($property) - 1);

                if (!array_key_exists($key, $parsed)) {
                    $parsedMap[$key] = [];
                }
                $parsedMap[$key][] = $property;
            }
        }

        return $parsedMap;
    }
}