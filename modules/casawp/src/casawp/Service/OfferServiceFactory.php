<?php
namespace casawp\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class OfferServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $categoryService = $container->get('CasasoftCategory');
        $numvalService = $container->get('CasasoftNumval');
        $messengerService = $container->get('CasasoftMessenger');
        $utilityService = $container->get('CasasoftUtility');
        $featureService = $container->get('CasasoftFeature');
        $integratedOfferService = $container->get('CasasoftIntegratedOffer');
        $formService = $container->get('casawpFormService');

        return new OfferService(
            $categoryService,
            $numvalService,
            $messengerService,
            $utilityService,
            $featureService,
            $integratedOfferService,
            $formService
        );
    }
}
