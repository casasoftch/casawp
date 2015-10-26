<?php
namespace CasasoftStandards\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FeatureServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');

        $service = new FeatureService($translator);
        
        return $service;
    }
}