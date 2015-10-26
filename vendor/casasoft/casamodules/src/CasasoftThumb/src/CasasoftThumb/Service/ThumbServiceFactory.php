<?php
namespace CasasoftThumb\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class ThumbServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $service = new ThumbService();

        $config = $serviceLocator->get('config');
        $r_config = array();
        if (isset($config['casasoft-thumb'])) {
            $r_config = $config['casasoft-thumb'];
        }
        $service->setConfig($r_config);
        
        return $service;
    }
}