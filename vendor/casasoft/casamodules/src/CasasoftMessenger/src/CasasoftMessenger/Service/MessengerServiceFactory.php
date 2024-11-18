<?php
namespace CasasoftMessenger\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class MessengerServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        // Retrieve necessary services
        $translator = $container->get('translator');  // Note: Use lowercase 'translator' as the service name
        //$htmlPurifier = $container->get('htmlPurifier'); // Uncomment if available
        $htmlPurifier = false; // Set as false if not available

        // Instantiate MessengerService with dependencies
        $service = new MessengerService($translator, $htmlPurifier);

        // Retrieve configuration specific to the messenger service
        $config = $container->get('Config');
        $r_config = isset($config['casasoft-messenger']) ? $config['casasoft-messenger'] : [];

        // Apply configuration to the service
        $service->setConfig($r_config);

        return $service;
    }
}
