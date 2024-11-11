<?php
namespace casawp\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FormSettingServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // Instantiate the FormSettingService here and pass dependencies if needed
        $service = new FormSettingService();
        
        // If FormSettingService has dependencies from the container, inject them here:
        // $dependency = $container->get(DependencyClass::class);
        // $service->setDependency($dependency);
        
        return $service;
    }
}
