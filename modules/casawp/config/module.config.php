<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'casawpOffer'   => 'casawp\Service\OfferServiceFactory',
            'casawpProject'   => 'casawp\Service\ProjectServiceFactory',
            'casawpQuery'   => 'casawp\Service\QueryServiceFactory',
            'casawpFormService' => 'casawp\Service\FormServiceFactory',
            'casawpFormSettingService' => 'casawp\Service\FormSettingServiceFactory',
            //'casawpCollection' => 'casawp\Service\OfferCollectionServiceFactory',

            // Add the Casasoft services
            'CasasoftCategory' => \CasasoftStandards\Service\CategoryServiceFactory::class,
            'CasasoftNumval' => \CasasoftStandards\Service\NumvalServiceFactory::class,
            'CasasoftFeature' => \CasasoftStandards\Service\FeatureServiceFactory::class,
            'CasasoftUtility' => \CasasoftStandards\Service\UtilityServiceFactory::class,
            'CasasoftIntegratedOffer' => \CasasoftStandards\Service\IntegratedOfferServiceFactory::class,
            'CasasoftMessenger' => \CasasoftMessenger\Service\MessengerServiceFactory::class,
        )
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'casawp' => __DIR__ . '/../view',
        ),
    )
);
