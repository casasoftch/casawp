<?php

return array(
    'view_helpers' => array(
        'factories' => array(
        ),
    ),
    'service_manager' => array(
        'factories' => array(
            'CasasoftMessenger'   => 'CasasoftMessenger\Service\MessengerServiceFactory',
        )
    ),
    'translator' => array( 
        'translation_file_patterns' => array( 
            array( 
                'type'     => 'gettext',
                'base_dir'     => __DIR__ . '/../language', 
                'pattern'  => '%s.mo',
                'text_domain'  => 'casasoft-messenger', 
            ), 
        ), 
    ), 
);
