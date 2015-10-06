<?php
namespace CasasoftGeo\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class GeoServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $service = new GeoService();

        $config = $serviceLocator->get('config');
        $r_config = array();
        if (isset($config['casasoft-geo'])) {
            $r_config = $config['casasoft-geo'];
        }
        $service->setConfig($r_config);
        
        return $service;
    }
}