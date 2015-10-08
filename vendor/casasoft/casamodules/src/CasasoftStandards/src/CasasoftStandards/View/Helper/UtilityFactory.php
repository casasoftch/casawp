<?php
namespace CasasoftStandards\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class UtilityFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();
        $utilityService = $serviceLocator->get('UtilityService');
        
        $helper = new Utility($utilityService);
        
        return $helper;
    }
}