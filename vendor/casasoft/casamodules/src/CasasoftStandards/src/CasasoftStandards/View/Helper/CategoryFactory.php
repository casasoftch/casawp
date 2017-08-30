<?php
namespace CasasoftStandards\View\Helper;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class CategoryFactory implements FactoryInterface
{
    function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array$options = NULL)
    {
        $categoryService = $container->get('CasasoftCategory');
        $helper = new Category($categoryService);
        return $helper;
    }

    public function createService(ServiceLocatorInterface $viewHelperManager)
    {
        $serviceLocator = $viewHelperManager->getServiceLocator();
        $categoryService = $serviceLocator->get('CasasoftCategory');

        $helper = new Category($categoryService);

        return $helper;
    }
}
