<?php
namespace casawp\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ProjectServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        // Retrieve dependencies from the service container
        $categoryService = $container->get('CasasoftCategory');
        $numvalService = $container->get('CasasoftNumval');
        $featureService = $container->get('CasasoftFeature');
        $messengerService = $container->get('CasasoftMessenger');
        $utilityService = $container->get('CasasoftUtility');
        $integratedOfferService = $container->get('CasasoftIntegratedOffer');

        // Instantiate and return the ProjectService
        return new ProjectService(
            $categoryService,
            $numvalService,
            $messengerService,
            $utilityService,
            $featureService,
            $integratedOfferService
        );
    }
}
