<?php
namespace CasasoftStandards\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class FeatureServiceFactory implements FactoryInterface
{

    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = NULL){
      $translator = $container->get('MvcTranslator');
      //$viewRenderer = $serviceLocator->get('viewRenderer');

      $service = new FeatureService($translator);

      return $service;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');

        $service = new FeatureService($translator);

        return $service;
    }
}
