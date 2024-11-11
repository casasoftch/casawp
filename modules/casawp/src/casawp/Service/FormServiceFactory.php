<?php
namespace casawp\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class FormServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        // Instantiate FormService, injecting any dependencies if needed
        $service = new FormService();
        
        // If FormService has dependencies, retrieve them from the container:
        // $dependency = $container->get(DependencyClass::class);
        // $service->setDependency($dependency);
        
        return $service;
    }
}
