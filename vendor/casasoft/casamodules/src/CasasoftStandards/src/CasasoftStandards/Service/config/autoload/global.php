<?php
/**
 * Global Configuration Override
 *
 * You can use this file for overriding configuration values from modules, etc.
 * You would place values in here that are agnostic to the environment and not
 * sensitive to security.
 *
 * @NOTE: In practice, this file will typically be INCLUDED in your source
 * control, so do not include passwords or other sensitive information in this
 * file.
 */

return [
  'view_manager' => [
    'display_exceptions' => false,
    'display_errors' => false,
  ],
  'gateway' => [
    'url' => 'https://casagateway.ch',
    'transient_enabled' => true,
    'transient_ttl' => 600
  ],
  'casa-log-service' =>[
      'software' => 'icasa',
      'url' => 'http://logs.casasoft.ch',
      'username' => 'casasoft',
      'password' => 'Casa4Soft!',
  ],
  'comparis' => array(
    'siteID' => 900
  ),
  'casaauth' => array(
      'client_id' => 'casagateway',
      'client_secret' => '7zNmbT7qHLPbTsZPQDitCtPLbRG',
      'api_url' => 'https://auth.casasoft.com',
      'storage_dir' => "data/client_keycache_casaauth/",
  ),
  'doctrine' => array(

    'connection' => array(
        'odm_default' => array(
                  'server'           => 'mongo',
//                'port'             => '27017',
//                'connectionString' => null,
//                'user'             => null,
//                'password'         => null,
//                'dbname'           => null,
//                'options'          => array()
        ),
    ),

    'configuration' => array(
        'odm_default' => array(
            'generate_proxies'   => false,
//                'metadata_cache'     => 'array',
//
//                'driver'             => 'odm_default',
//
//                'generate_proxies'   => true,
//                'proxy_dir'          => 'data/DoctrineMongoODMModule/Proxy',
//                'proxy_namespace'    => 'DoctrineMongoODMModule\Proxy',
//
//                'generate_hydrators' => true,
                  'hydrator_dir'       => 'data/DoctrineMongoODMModule/Hydrator',
                  'hydrator_namespace' => 'DoctrineMongoODMModule\Hydrator',
//
//                'default_db'         => null,
//
//                'filters'            => array(),  // array('filterName' => 'BSON\Filter\Class'),
//
//                'logger'             => null // 'DoctrineMongoODMModule\Logging\DebugStack'
        )
    ),

    'driver' => [
         'Application_driver' => [
            'class' => 'Doctrine\ODM\MongoDB\Mapping\Driver\AnnotationDriver',
            'paths' => ['module/Application/src/Document']
        ],
        'odm_default' => [
            'drivers' => [
              'Application\Document' => 'Application_driver'
            ]
        ]
    ],

    'documentmanager' => array(
        'odm_default' => array(
//                'connection'    => 'odm_default',
//                'configuration' => 'odm_default',
//                'eventmanager' => 'odm_default'
        )
    ),

    'eventmanager' => array(
        'odm_default' => array(
            'subscribers' => array()
        )
    ),
  ),

];
