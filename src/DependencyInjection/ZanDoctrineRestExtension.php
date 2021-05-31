<?php


namespace Zan\DoctrineRestBundle\DependencyInjection;


use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Zan\DoctrineRestBundle\Controller\EntityDataController;

class ZanDoctrineRestExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );

        $loader->load('services.yaml');

        // Hint about annotated classes: https://symfony.com/doc/current/bundles/extension.html#adding-classes-to-compile
        $this->addAnnotatedClassesToCompile([
            EntityDataController::class,
        ]);
    }
}