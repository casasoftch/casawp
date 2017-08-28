<?php
namespace CasasoftStandards\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class FeatureFactory implements FactoryInterface
{
    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = NULL)
    {
        $featureService = $container->get('CasasoftFeature');
        $helper = new Feature($featureService);
        return $helper;
    }

    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();
        $featureService = $serviceLocator->get('CasasoftFeature');

        $helper = new Feature($featureService);

        return $helper;
    }
}
