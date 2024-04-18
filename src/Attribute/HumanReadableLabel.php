<?php

namespace Zan\DoctrineRestBundle\Attribute;

/**
 * Specifies a human-readable label for a property
 */
#[\Attribute]
class HumanReadableLabel
{
    private string $label;

    public function __construct(string $label)
    {
        $this->label = $label;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}