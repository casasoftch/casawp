<?php
namespace CasasoftStandards\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class NumvalFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $sl)
    {
        $serviceLocator = $sl->getServiceLocator();
        $numvalService = $serviceLocator->get('CasasoftNumval');
        
        $helper = new Numval($numvalService);
        
        return $helper;
    }
}