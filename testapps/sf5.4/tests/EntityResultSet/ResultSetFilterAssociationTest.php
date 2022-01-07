<?php

namespace App\Tests\EntityResultSet;

use App\Entity\User;
use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zan\DoctrineRestBundle\EntityResultSet\GenericEntityResultSet;
use Zan\DoctrineRestBundle\EntityResultSet\ResultSetFilterCollection;

class ResultSetFilterAssociationTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testJoinedStringEquals(): void
    {
        $filters = ResultSetFilterCollection::buildFromArray([
            [
                'property' => 'defaultGroup.label',
                'operator' => '=',
                'value' => 'Test Group',
            ],
        ]);

        $entities = $this->getResults($filters, User::class);

        $this->assertGreaterThan(0, $entities);

        // Ensure all entities have the correct group label
        /** @var User $user */
        foreach ($entities as $user) {
            if ($user->getDefaultGroup()->getLabel() !== 'Test Group') {
                $this->fail('Unexpected group label');
            }
        }
    }

    /**
     * Tests a multi-table join: User -> UserGroup -> Group -> label
     */
    public function testIntermediateJoinStringEquals(): void
    {
        $filters = ResultSetFilterCollection::buildFromArray([
            [
                'property' => 'userGroupMappings.group.label',
                'operator' => '=',
                'value' => 'Test Group',
            ],
        ]);

        $entities = $this->getResults($filters, User::class);

        $this->assertGreaterThan(0, $entities);

        // Ensure all entities have the correct group label
        /** @var User $user */
        foreach ($entities as $user) {
            $foundGroup = false;
            foreach ($user->getGroups() as $group) {
                if ($group->getLabel() === 'Test Group') $foundGroup = true;
            }

            if (!$foundGroup) {
                $this->fail('Group was not present');
            }
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