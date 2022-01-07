<?php

namespace App\Tests\EntityResultSet;

use App\Entity\User;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilter;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilterCollection;

class ResultSetFilterTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testStringEquals(): void
    {
        $filters = ResultSetFilterCollection::buildFromArray([
           [
               'property' => 'username',
               'operator' => '=',
               'value' => 'test1',
           ],
        ]);

        $entities = $this->getResults($filters, User::class);

        $this->assertCount(1, $entities);
        $this->assertEquals('test1', $entities[0]->getUsername());
    }

    public function testIntEquals(): void
    {
        $filters = ResultSetFilterCollection::buildFromArray([
            [
                'property' => 'numFailedLogins',
                'operator' => '=',
                'value' => 0,
            ],
        ]);

        $entities = $this->getResults($filters, User::class);

        $this->assertGreaterThan(0, $entities);

        // Ensure hasFailedLogins does not appear in the result set
        foreach ($entities as $entity) {
            if ($entity->getUsername() === 'hasFailedLogins') $this->fail('hasFailedLogins user appeared in the result set');
        }
    }

    /**
     * Helper method to create a GenericEntityResultSet and apply a $filterCollection
     */
    protected function getResults(ResultSetFilterCollection $filterCollection, string $baseEntity)
    {
        $resultSet = new GenericEntityResultSet(
            $baseEntity,
            static::getContainer()->get(EntityManagerInterface::class),
            static::getContainer()->get(Reader::class)
        );

        $resultSet->setDataFilterCollection($filterCollection);

        return $resultSet->getResults();
    }
}
