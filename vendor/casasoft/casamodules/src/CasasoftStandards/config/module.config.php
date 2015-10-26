<?php

return array(
    'view_helpers' => array(
        'factories' => array(
            'casasoftCategory' => 'CasasoftStandards\View\Helper\Category',
            'casasoftFeature' => 'CasasoftStandards\View\Helper\Feature',
            'casasoftNumval' => 'CasasoftStandards\View\Helper\Numval',
            'casasoftUtility' => 'CasasoftStandards\View\Helper\Utility',
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'CasasoftCategory'   => 'CasasoftStandards\Service\CategoryServiceFactory',
            'CasasoftFeature'   => 'CasasoftStandards\Service\FeatureServiceFactory',
            'CasasoftNumval'   => 'CasasoftStandards\Service\NumvalServiceFactory',
            'CasasoftUtility'   => 'CasasoftStandards\Service\UtilityServiceFactory',
        )
    ),
    'translator' => array( 
        'translation_file_patterns' => array( 
            array( 
                'type'     => 'gettext',
                'base_dir'     => __DIR__ . '/../language', 
                'pattern'  => '%s.mo',
                'text_domain'  => 'casasoft-standards', 
            ), 
        ), 
    ), 
);
