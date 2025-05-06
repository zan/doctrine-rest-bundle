<?php

namespace Zan\DoctrineRestBundle\EntityResultSet;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Zan\DoctrineRestBundle\Permissions\PermissionsCalculatorFactory;

/**
 * Contains helper methods for getting result sets as well as single entities
 */
class EntityResultSetFactory
{
    private EntityManagerInterface $em;

    private PermissionsCalculatorFactory $permissionsCalculatorFactory;

    public function __construct(
        EntityManagerInterface $em,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
    ) {
        $this->em = $em;
        $this->permissionsCalculatorFactory = $permissionsCalculatorFactory;
    }

    public function mustGetOneReadable(string $entityClassName, $identifier)
    {
        $resultSet = new GenericEntityResultSet($entityClassName, $this->em);

        $resultSet->addIdentifierFilter($identifier);

        return $resultSet->mustGetSingleResult();
    }
}