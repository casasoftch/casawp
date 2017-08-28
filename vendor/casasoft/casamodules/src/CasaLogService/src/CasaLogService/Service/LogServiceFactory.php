<?php
namespace CasaLogService\Service;

use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class LogServiceFactory implements FactoryInterface
{

  function __invoke(\Interop\Container\ContainerInterface $container, $requestedName, array $options = NULL){
    $service = new LogService();

    $main_config = $container->get('Config');
    if (isset($main_config['casa-log-service'])) {
        $requested_config = $main_config['casa-log-service'];
    } else {
        $requested_config = array();
    }
    $default_config = [
        'software' => 'casa-messenger',
        'url' => '',
        'username' => '',
        'password' => '',
        'zend_logger_cap' => 6,
        'slack_hook' => false,
        'shutdown' => [
            'activate' => true,
            'report_rogue_entries' => true,
            'report_error_codes' => [
                E_ERROR,
                E_WARNING,
                E_PARSE,
                E_NOTICE,
                E_CORE_ERROR,
                E_CORE_WARNING,
                E_COMPILE_ERROR,
                E_COMPILE_WARNING,
                E_USER_ERROR,
                E_USER_WARNING,
                E_USER_NOTICE,
                E_STRICT,
                E_RECOVERABLE_ERROR,
                E_DEPRECATED,
                E_USER_DEPRECATED,
                E_ALL,
            ]
        ]
    ];
    $config = array_merge($default_config, $requested_config);
    $service->setConfig($config);

    return $service;
  }

    public function createService(ServiceLocatorInterface $serviceLocator)
    {
        $viewRenderer = $serviceLocator->get('viewRenderer');
        $service = new LogService($viewRenderer);

        $main_config = $serviceLocator->get('Config');
        if (isset($main_config['casa-log-service'])) {
            $requested_config = $main_config['casa-log-service'];
        } else {
            $requested_config = array();
        }
        $default_config = [
            'software' => 'casa-messenger',
            'url' => '',
            'username' => '',
            'password' => '',
            'zend_logger_cap' => 6,
            'slack_hook' => false,
            'shutdown' => [
                'activate' => true,
                'report_rogue_entries' => true,
                'report_error_codes' => [
                    E_ERROR,
                    E_WARNING,
                    E_PARSE,
                    E_NOTICE,
                    E_CORE_ERROR,
                    E_CORE_WARNING,
                    E_COMPILE_ERROR,
                    E_COMPILE_WARNING,
                    E_USER_ERROR,
                    E_USER_WARNING,
                    E_USER_NOTICE,
                    E_STRICT,
                    E_RECOVERABLE_ERROR,
                    E_DEPRECATED,
                    E_USER_DEPRECATED,
                    E_ALL,
                ]
            ]
        ];
        $config = array_merge($default_config, $requested_config);
        $service->setConfig($config);

        return $service;
    }
}
