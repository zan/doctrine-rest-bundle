<?php

namespace App\Test;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ApiTestCase extends WebTestCase
{
    protected function loginAs(string $username)
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine.orm.default_entity_manager');

        $user = $em->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        $client->loginUser($user);

        return $client;
    }

    protected function request(KernelBrowser $client, string $method, string $uri, array $params = [])
    {
        $client->jsonRequest($method, $uri, $params);

        $response = $client->getResponse();

        $decodedBody = json_decode($response->getContent(), true);

        if (!$decodedBody['success']) {
            throw new \ErrorException("API call failed: " . $decodedBody['errorMessage']);
        }

        return $decodedBody;
    }
}