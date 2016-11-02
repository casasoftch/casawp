<?php
namespace casawp\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ProjectServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
    	$cs = $serviceLocator->get('CasasoftCategory');
    	$ns = $serviceLocator->get('CasasoftNumval');
    	$fs = $serviceLocator->get('CasasoftFeature');
    	$messenger = $serviceLocator->get('CasasoftMessenger');
    	$us = $serviceLocator->get('CasasoftUtility');
        $ios = $serviceLocator->get('CasasoftIntegratedOffer');
        $service = new ProjectService($cs, $ns, $messenger, $us, $fs, $ios);
        return $service;
    }
}