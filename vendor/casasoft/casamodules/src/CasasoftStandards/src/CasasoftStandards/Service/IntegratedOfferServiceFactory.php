<?php
namespace CasasoftStandards\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class IntegratedOfferServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $translator = $serviceLocator->get('Translator');
        $service = new IntegratedOfferService($translator);
        return $service;
    }
}