<?php
namespace CasasoftStandards\View\Helper;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

class UtilityFactory implements FactoryInterface
{
    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array$options = NULL)
    {
        $utilityService = $container->get('CasasoftUtility');
        $helper = new Category($utilityService);
        return $helper;
    }

    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();
        $utilityService = $serviceLocator->get('CasasoftUtility');

        $helper = new Utility($utilityService);

        return $helper;
    }
}
