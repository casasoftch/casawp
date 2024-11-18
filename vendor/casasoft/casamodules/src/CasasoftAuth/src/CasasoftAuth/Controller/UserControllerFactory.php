<?php 
namespace CasasoftAuth\Controller;
use \Laminas\ServiceManager\FactoryInterface;
use \Laminas\ServiceManager\ServiceLocatorInterface;
 
class UserControllerFactory implements FactoryInterface
{
    public function createService(ServiceLocatorInterface $serviceLocator) {
        $sm   = $serviceLocator->getServiceLocator();

        $em          = $sm->get('doctrine.entitymanager.orm_default');
        $authService = $sm->get('CasasoftAuthService');
        $emailService = $sm->get('CasasoftEmailService');
        $translator = $sm->get('translator');

        $controller = new UserController($em, $authService, $emailService, $translator);
        return $controller;
    }
}