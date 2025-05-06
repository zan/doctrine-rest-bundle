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

    private Reader $annotationReader;

    public function __construct(
        EntityManagerInterface $em,
        PermissionsCalculatorFactory $permissionsCalculatorFactory,
        Reader $annotationReader
    ) {
        $this->em = $em;
        $this->permissionsCalculatorFactory = $permissionsCalculatorFactory;
        $this->annotationReader = $annotationReader;
    }

    public function mustGetOneReadable(string $entityClassName, $identifier)
    {
        $resultSet = new GenericEntityResultSet($entityClassName, $this->em);

        $resultSet->addIdentifierFilter($identifier);

        return $resultSet->mustGetSingleResult();
    }
}