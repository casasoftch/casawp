<?php
namespace CasasoftStandards\View\Helper;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class NumvalFactory implements FactoryInterface
{

  function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array$options = NULL)
  {
      $numvalService = $container->get('CasasoftNumval');
      $helper = new Numval($numvalService);
      return $helper;
  }

    public function createService(ServiceLocatorInterface $sl)
    {
        $serviceLocator = $sl->getServiceLocator();
        $numvalService = $serviceLocator->get('CasasoftNumval');

        $helper = new Numval($numvalService);

        return $helper;
    }
}
