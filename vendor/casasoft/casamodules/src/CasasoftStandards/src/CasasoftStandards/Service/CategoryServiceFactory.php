<?php
namespace CasasoftStandards\Service;

use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CategoryServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, ?array $options = null)
    {
        $translator = $container->get('MvcTranslator');
        if ($translator === null) {
            throw new \Exception('MvcTranslator service is null in CategoryServiceFactory');
        }

        $service = new CategoryService($translator);

        return $service;
    }
}
