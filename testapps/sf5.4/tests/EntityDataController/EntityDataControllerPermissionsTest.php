<?php

namespace App\tests\EntityDataController;

use App\Test\ApiTestCase;

class EntityDataControllerPermissionsTest extends ApiTestCase
{
    public function testNoListAccessByDefault()
    {
        $client = $this->loginAs('test1');

        $this->expectException(\ErrorException::class);
        $this->request($client, 'GET', '/api/zan/drest/entity/App.Entity.NoApiAccess');
    }

    public function testNoReadAccessByDefault()
    {
        $client = $this->loginAs('test1');

        $this->expectException(\ErrorException::class);
        $response = $this->request($client, 'GET', '/api/zan/drest/entity/App.Entity.NoApiAccess/noApiAccess1');
        $raw = $response['data'];
    }

    public function testRead()
    {
        $client = $this->loginAs('test1');

        $this->expectException(\ErrorException::class);
        $response = $this->request($client, 'GET', '/api/zan/drest/entity/App.Entity.SimpleEntity/simple1');
        $raw = $response['data'];

        $this->assertEquals('simple1', $raw['publicId']);
        dump($raw);
    }
}