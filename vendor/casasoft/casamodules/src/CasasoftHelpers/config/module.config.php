<?php

return array(
    'view_helpers' => array(
        'factories' => array(
            'pagination' => 'CasasoftHelpers\View\Helper\PaginateMeFactory',
            'relativeDate' => 'CasasoftHelpers\View\Helper\RelativedateFactory',
        ),
    ),
    'translator' => array( 
        'translation_file_patterns' => array( 
            array( 
                'type'     => 'gettext',
                'base_dir'     => __DIR__ . '/../language', 
                'pattern'  => '%s.mo',
                'text_domain'  => 'casasoft-helpers', 
            ), 
        ), 
    ), 
);
