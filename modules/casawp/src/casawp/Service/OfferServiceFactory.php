<?php
namespace casawp\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;
use casawp\Service\FormService;
use CasasoftStandards\Service\CategoryService;
use CasasoftStandards\Service\NumvalService;
use CasasoftMessenger\Service\MessengerService;
use CasasoftStandards\Service\UtilityService;
use CasasoftStandards\Service\FeatureService;
use CasasoftStandards\Service\IntegratedOfferService;

class OfferServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        /** @var CategoryService $categoryService */
        $categoryService = $container->get(CategoryService::class);
        /** @var NumvalService $numvalService */
        $numvalService = $container->get(NumvalService::class);
        /** @var MessengerService $messengerService */
        $messengerService = $container->get(MessengerService::class);
        /** @var UtilityService $utilityService */
        $utilityService = $container->get(UtilityService::class);
        /** @var FeatureService $featureService */
        $featureService = $container->get(FeatureService::class);
        /** @var IntegratedOfferService $integratedOfferService */
        $integratedOfferService = $container->get(IntegratedOfferService::class);
        /** @var FormService $formService */
        $formService = $container->get(FormService::class);

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
