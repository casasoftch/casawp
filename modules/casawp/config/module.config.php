<?php

use Laminas\ServiceManager\Factory\InvokableFactory;

return [
    'service_manager' => [
        'factories' => [
            'casawpOffer' => casawp\Service\OfferServiceFactory::class,
            'casawpProject' => casawp\Service\ProjectServiceFactory::class,
            'casawpQuery' => casawp\Service\QueryServiceFactory::class,
            'casawpFormService' => casawp\Service\FormServiceFactory::class,
            'casawpFormSettingService' => casawp\Service\FormSettingServiceFactory::class,
            // Casasoft services...
            'CasasoftCategory' => CasasoftStandards\Service\CategoryServiceFactory::class,
            'CasasoftNumval' => CasasoftStandards\Service\NumvalServiceFactory::class,
            'CasasoftFeature' => CasasoftStandards\Service\FeatureServiceFactory::class,
            'CasasoftUtility' => CasasoftStandards\Service\UtilityServiceFactory::class,
            'CasasoftIntegratedOffer' => CasasoftStandards\Service\IntegratedOfferServiceFactory::class,
            'CasasoftMessenger' => CasasoftMessenger\Service\MessengerServiceFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            'casawp' => __DIR__ . '/../view',
        ],
    ],
    'view_helpers' => [
        'aliases' => [
            // Form view helper aliases
            'form' => Laminas\Form\View\Helper\Form::class,
            'formLabel' => Laminas\Form\View\Helper\FormLabel::class,
            'formElement' => Laminas\Form\View\Helper\FormElement::class,
            'formInput' => Laminas\Form\View\Helper\FormInput::class,
            'formSelect' => Laminas\Form\View\Helper\FormSelect::class,
            'formTextarea' => Laminas\Form\View\Helper\FormTextarea::class,
            'formCheckbox' => Laminas\Form\View\Helper\FormCheckbox::class,
            'formRadio' => Laminas\Form\View\Helper\FormRadio::class,
            'formMultiCheckbox' => Laminas\Form\View\Helper\FormMultiCheckbox::class,
            // Add other helpers as needed
        ],
        'factories' => [
            Laminas\Form\View\Helper\Form::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormLabel::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormElement::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormInput::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormSelect::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormTextarea::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormCheckbox::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormRadio::class => InvokableFactory::class,
            Laminas\Form\View\Helper\FormMultiCheckbox::class => InvokableFactory::class,
            // Add other factories as needed
        ],
    ],
];
