<?php

namespace App\Tests\EntityDataController;

use App\Test\ApiTestCase;

class EntityDataControllerBasicTest extends ApiTestCase
{
    public function testList()
    {
        $client = $this->loginAs('test1');

        $response = $this->request($client, 'GET', '/api/zan/drest/entity/App.Entity.User');

        $this->assertGreaterThan(0, $response['total']);

        // Verify 'test1' appears in the results
        $found = false;
        foreach ($response['data'] as $data) {
            if ('test1' === $data['username']) $found = true;
        }
        $this->assertTrue($found);
    }

    public function testUpdate()
    {
        $client = $this->loginAs('test1');

        $params = [
            'label' => 'label changed',
        ];

        $response = $this->request($client, 'PUT', '/api/zan/drest/entity/App.Entity.SimpleEntity/simple1', $params);

        $this->assertEquals($response['data']['label'], 'label changed');
    }
}