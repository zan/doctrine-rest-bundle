<?php

namespace Zan\DoctrineRestBundle\Controller;

use Doctrine\Common\Annotations\Reader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Zan\CommonBundle\Util\RequestUtils;
use Zan\CommonBundle\Util\ZanArray;
use Zan\DoctrineRestBundle\EntitySerializer\MinimalEntitySerializer;

class LoggedInUserController
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[Route('/logged-in-user')]
    public function getLoggedInUser(
        Request $request,
        EntityManagerInterface $em,
        Reader $annotationReader,
    ) {
        $params = RequestUtils::getParameters($request);
        $user = $this->security->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'code' => 'zan.noLoggedInUser',
                'message' => 'No logged in user found',
            ]);
        }

        $serializer = new MinimalEntitySerializer($em, $annotationReader);

        $responseFields = [];
        if ($request->query->has('responseFields')) {
            $responseFields = ZanArray::createFromString($params['responseFields']);
        }

        return new JsonResponse([
            'success' => true,
            'data' => $serializer->serialize($user, $responseFields),
        ]);
    }
}