<?php
namespace CasasoftAuth\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

use Laminas\Permissions\Acl\Acl;

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