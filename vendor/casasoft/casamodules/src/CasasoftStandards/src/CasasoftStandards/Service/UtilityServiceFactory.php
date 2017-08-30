<?php
namespace CasasoftStandards\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class UtilityServiceFactory implements FactoryInterface
{
    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = NULL){
      $translator = $container->get('MvcTranslator');
      //$viewRenderer = $serviceLocator->get('viewRenderer');

      $service = new UtilityService($translator);

      return $service;
    }
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');
        //$viewRenderer = $serviceLocator->get('viewRenderer');

        $service = new UtilityService($translator);

        return $service;
    }
}
