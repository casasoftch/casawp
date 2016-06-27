<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'casawpOffer'   => 'casawp\Service\OfferServiceFactory',
            'casawpProject'   => 'casawp\Service\ProjectServiceFactory',
            'casawpQuery'   => 'casawp\Service\QueryServiceFactory',
            //'casawpCollection' => 'casawp\Service\OfferCollectionServiceFactory',
        )
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'casawp' => __DIR__ . '/../view',
        ),
    )
);
