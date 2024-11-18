<?php
namespace CasasoftStandards\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class NumvalServiceFactory implements FactoryInterface
{

    public function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = null){
        $translator = $container->get('MvcTranslator');
        //$viewRenderer = $serviceLocator->get('viewRenderer');

        $service = new NumvalService($translator);

        return $service;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');
        //$viewRenderer = $serviceLocator->get('viewRenderer');

        $service = new NumvalService($translator);

        return $service;
    }
}
