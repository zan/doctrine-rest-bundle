<?php

namespace Zan\DoctrineRestBundle\Permissions;

class PermissionsCalculatorRegistry
{
    /** @var PermissionsCalculatorInterface[]  */
    private array $calculators = [];

    /**
     * @param PermissionsCalculatorInterface[] $availableCalculators
     */
    public function __construct(iterable $availableCalculators)
    {
        foreach ($availableCalculators as $calculator) {
            $this->calculators[] = $calculator;
        }
    }

    /**
     * @return array|PermissionsCalculatorInterface[]
     */
    public function getCalculators(): array
    {
        return $this->calculators;
    }
}