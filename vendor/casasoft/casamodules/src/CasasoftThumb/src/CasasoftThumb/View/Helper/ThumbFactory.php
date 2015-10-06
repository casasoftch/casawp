<?php
namespace CasasoftThumb\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ThumbFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();
        $thumbService = $serviceLocator->get('CasasoftThumbService');
        
        $helper = new Thumb($thumbService);
        
        return $helper;
    }
}