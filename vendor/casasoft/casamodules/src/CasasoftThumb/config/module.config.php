<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'CasasoftThumbService'   => 'CasasoftThumb\Service\ThumbServiceFactory',
        )
    ),
    'view_helpers' => array(
        'factories' => array(
            'thumb' => 'CasasoftThumb\View\Helper\ThumbFactory',
        ),
    ),
);
