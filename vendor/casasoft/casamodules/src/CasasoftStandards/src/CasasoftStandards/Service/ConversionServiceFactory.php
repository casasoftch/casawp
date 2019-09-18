<?php
namespace CasasoftStandards\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ConversionServiceFactory implements FactoryInterface
{

    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = NULL){
      $translator = $container->get('MvcTranslator');
      $numvalService = $container->get('CasasoftNumval');
      $categoryService = $container->get('CasasoftCategory');
      $featureService = $container->get('CasasoftFeature');
      $utilityService = $container->get('CasasoftUtility');
      $service = new ConversionService($translator, $numvalService, $categoryService, $featureService, $utilityService);

      return $service;
    }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');
        $numvalService = $serviceLocator->get('CasasoftNumval');
        $categoryService = $serviceLocator->get('CasasoftCategory');
        $featureService = $serviceLocator->get('CasasoftFeature');
        $utilityService = $serviceLocator->get('CasasoftUtility');
        $service = new ConversionService($translator, $numvalService, $categoryService, $featureService, $utilityService);

        return $service;
    }
}
