<?php
namespace casawp\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

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