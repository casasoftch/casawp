<?php
namespace CasasoftAuth\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Zend\Permissions\Acl\Acl;

class AclServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $acl = new Acl();
        $service = new AclService($acl);
        
        $config = $serviceLocator->get('config');
        $r_config = array();
        if (isset($config['casasoft-auth'])) {
            $r_config = $config['casasoft-auth'];
        }
        $service->setConfig($r_config);

        return $service;
    }
}