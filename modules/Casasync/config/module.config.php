<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'CasasyncOffer'   => 'Casasync\Service\OfferServiceFactory',
            'CasasyncQuery'   => 'Casasync\Service\QueryServiceFactory',
            //'CasasyncCollection' => 'Casasync\Service\OfferCollectionServiceFactory',
        )
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            'casawp' => __DIR__ . '/../view',
        ),
    )
);
