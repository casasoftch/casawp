<?php
namespace CasasoftStandards\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use CasasoftStandards\Service\FeatureService;
use Laminas\I18n\Translator\TranslatorInterface;

class FeatureServiceFactory implements FactoryInterface
{
  public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
  {
      /** @var TranslatorInterface $translator */
      $translator = $container->get('translator');
      return new FeatureService($translator);
  }
}
