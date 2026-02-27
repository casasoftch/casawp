<?php
namespace CasasoftStandards\Service;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Laminas\I18n\Translator\TranslatorInterface;

class HeatServiceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        /** @var TranslatorInterface $translator */
        $translator = $container->get('translator');

        // Optional but recommended in WP context: sync locale to WordPress
        $locale = function_exists('determine_locale') ? determine_locale() : get_locale();
        if (method_exists($translator, 'setLocale')) {
            $translator->setLocale($locale);
        }

        return new HeatService($translator);
    }
}