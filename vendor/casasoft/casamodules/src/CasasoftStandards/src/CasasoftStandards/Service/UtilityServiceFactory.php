<?php
namespace CasasoftStandards\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CasasoftStandards\Service\UtilityService;
use Laminas\I18n\Translator\TranslatorInterface;

class UtilityServiceFactory implements FactoryInterface
{
  public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
  {
      /** @var TranslatorInterface $translator */
      $translator = $container->get('translator');
      return new UtilityService($translator);
  }
}
