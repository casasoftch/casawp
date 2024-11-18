<?php
namespace CasasoftAuth\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class AuthServiceFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $auth = $serviceLocator->get('doctrine.authenticationservice.orm_default');
        $em = $serviceLocator->get('doctrine.entitymanager.orm_default');
        $storage = $serviceLocator->get('doctrine.authenticationstorage.orm_default');
        $request = $serviceLocator->get('request');
        $aclService = $serviceLocator->get('aclService');
        //$session = $serviceLocator->get('Laminas\Session\SessionManager');
        $session = false;
        
        //$blamableListener = $serviceLocator->get('Gedmo\Blameable\BlameableListener');
        $blamableListener = false;
        
            
        $plugins = $serviceLocator->get('ControllerPluginManager');
        $service = new AuthService($auth, $em, $storage, $aclService, $request, $session, $blamableListener, $plugins);

        $config = $serviceLocator->get('config');
        $r_config = array();
        if (isset($config['casasoft-acl'])) {
            $r_config = $config['casasoft-acl'];
        }
        $service->setConfig($r_config);
        
        return $service;
    }
}