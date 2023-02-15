<?php


namespace Zan\DoctrineRestBundle\Permissions;


use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Zan\CommonBundle\Util\ZanAnnotation;
use Zan\DoctrineRestBundle\Annotation\ApiPermissions;

class PermissionsCalculatorFactory
{
    /** @var EntityManagerInterface */
    protected $em;

    /** @var Reader */
    protected $annotationReader;

    protected PermissionsCalculatorRegistry $calculatorRegistry;

    private array $cache = [];

    public function __construct(
        EntityManagerInterface $em,
        Reader $annotationReader,
        PermissionsCalculatorRegistry $calculatorRegistry,
    ) {
        $this->em = $em;
        $this->annotationReader = $annotationReader;
        $this->calculatorRegistry = $calculatorRegistry;
    }

    public function getPermissionsCalculator($entityClassName): ?PermissionsCalculatorInterface
    {
        if (is_object($entityClassName)) $entityClassName = get_class($entityClassName);

        if (array_key_exists($entityClassName, $this->cache)) return $this->cache[$entityClassName];

        /** @var ApiPermissions $apiPermissionsDeclaration */
        $apiPermissionsDeclaration = null;

        // Check for an attribute on the entity
        $reflClass = new \ReflectionClass($entityClassName);
        $attributes = $reflClass->getAttributes(ApiPermissions::class);
        if ($attributes) {
            $apiPermissionsDeclaration = $attributes[0]->newInstance();
        }

        // Try annotations next
        if (!$apiPermissionsDeclaration) {
            $apiPermissionsDeclaration = ZanAnnotation::getClassAnnotation(
                $this->annotationReader,
                ApiPermissions::class,
                $entityClassName
            );
            if (!$apiPermissionsDeclaration) {
                $this->cache[$entityClassName] = null;
                return null;
            }
        }


        // Look for the class as a registered service
        if ($apiPermissionsDeclaration->getPermissionsClass()) {
            foreach ($this->calculatorRegistry->getCalculators() as $calculator) {
                if (get_class($calculator) === $apiPermissionsDeclaration->getPermissionsClass()) {
                    $this->cache[$entityClassName] = $calculator;
                    return $calculator;
                }
            }

            // Calculator not found
            throw new \ErrorException(sprintf(
                '%s is using permissions calculator class %s but it is not defined as a service',
                $entityClassName,
                $apiPermissionsDeclaration->getPermissionsClass(),
            ));
        }

        // Otherwise, build a generic calculator based off information in the annotations
        $calculator = new GenericPermissionsCalculator();
        $calculator->setCreateRoles($apiPermissionsDeclaration->getCreateSpecification());
        $calculator->setReadRoles($apiPermissionsDeclaration->getReadSpecification());
        $calculator->setWriteRoles($apiPermissionsDeclaration->getWriteSpecification());
        $calculator->setDeleteRoles($apiPermissionsDeclaration->getDeleteSpecification());

        $this->cache[$entityClassName] = $calculator;
        return $calculator;
    }
}