<?php

return array(
    'view_helpers' => array(
        'factories' => array(
            'casasoftCategory' => 'CasasoftStandards\View\Helper\CategoryFactory',
            'casasoftFeature'  => 'CasasoftStandards\View\Helper\FeatureFactory',
            'casasoftNumval'   => 'CasasoftStandards\View\Helper\NumvalFactory',
            'casasoftUtility'  => 'CasasoftStandards\View\Helper\UtilityFactory',
            'casasoftLabel'    => 'CasasoftStandards\View\Helper\LabelFactory',
            'casasoftValue'    => 'CasasoftStandards\View\Helper\ValueFactory',
            'casasoftList'    => 'CasasoftStandards\View\Helper\GetListFactory',
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'CasasoftCategory'        => 'CasasoftStandards\Service\CategoryServiceFactory',
            'CasasoftFeature'         => 'CasasoftStandards\Service\FeatureServiceFactory',
            'CasasoftNumval'          => 'CasasoftStandards\Service\NumvalServiceFactory',
            'CasasoftUtility'         => 'CasasoftStandards\Service\UtilityServiceFactory',
            'CasasoftIntegratedOffer' => 'CasasoftStandards\Service\IntegratedOfferServiceFactory',
            'CasasoftConversion'      => 'CasasoftStandards\Service\ConversionServiceFactory',
            'CasasoftHeat'      => 'CasasoftStandards\Service\HeatServiceFactory',
        )
    ),
    'translator' => array(
        'translation_file_patterns' => array(
            array(
                'type'        => 'gettext',
                'base_dir'    => __DIR__ . '/../language',
                'pattern'     => '%s.mo',
                'text_domain' => 'casasoft-standards',
            ),
        ),
    ),
);
