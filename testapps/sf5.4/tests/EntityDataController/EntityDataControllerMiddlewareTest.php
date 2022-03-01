<?php

namespace App\Tests\EntityDataController;

use App\Test\ApiTestCase;

class EntityDataControllerMiddlewareTest extends ApiTestCase
{
    public function testBeforeCreate()
    {
        $client = $this->loginAs('test1');

        $label = 'created from test';
        $params = [
            'label' => $label,
        ];

        $response = $this->request($client, 'POST', '/api/zan/drest/entity/App.Entity.SimpleEntity', $params);

        // SimpleEntityMiddleware will set the middlewareValue property
        $this->assertEquals($label . ' before create middleware', $response['data']['middlewareValue']);
    }

    public function testAfterCreate()
    {
        $client = $this->loginAs('test1');

        $label = 'created from test';
        $params = [
            'label' => $label,
        ];

        $response = $this->request($client, 'POST', '/api/zan/drest/entity/App.Entity.SimpleEntity', $params);

        // SimpleEntityMiddleware will set the middlewareValue property
        $this->assertEquals($label . ' after create middleware', $response['data']['middlewareValue']);
    }
}