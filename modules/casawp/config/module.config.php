<?php

use Laminas\ServiceManager\Factory\InvokableFactory;

use CasasoftStandards\Service\CategoryService;
use CasasoftStandards\Service\CategoryServiceFactory;
use CasasoftStandards\Service\UtilityService;
use CasasoftStandards\Service\UtilityServiceFactory;
use CasasoftStandards\Service\NumvalService;
use CasasoftStandards\Service\NumvalServiceFactory;
use CasasoftStandards\Service\FeatureService;
use CasasoftStandards\Service\FeatureServiceFactory;

use CasasoftStandards\Service\IntegratedOfferService;
use CasasoftStandards\Service\IntegratedOfferServiceFactory;

use CasasoftMessenger\Service\MessengerService;
use CasasoftMessenger\Service\MessengerServiceFactory;

use casawp\Service\OfferService;
use casawp\Service\OfferServiceFactory;
use casawp\Service\ProjectService;
use casawp\Service\ProjectServiceFactory;
use casawp\Service\QueryService;
use casawp\Service\QueryServiceFactory;
use casawp\Service\FormService;
use casawp\Service\FormServiceFactory;
use casawp\Service\FormSettingService;
use casawp\Service\FormSettingServiceFactory;

return [
    'service_manager' => [
        'factories' => [
            OfferService::class => OfferServiceFactory::class,
            ProjectService::class => ProjectServiceFactory::class,
            QueryService::class => QueryServiceFactory::class,
            FormService::class => FormServiceFactory::class,
            FormSettingService::class => FormSettingServiceFactory::class,
            // Casasoft services
            CategoryService::class => CategoryServiceFactory::class,
            UtilityService::class => UtilityServiceFactory::class,
            NumvalService::class => NumvalServiceFactory::class,
            FeatureService::class => FeatureServiceFactory::class,
            MessengerService::class => MessengerServiceFactory::class,
            IntegratedOfferService::class => IntegratedOfferServiceFactory::class,
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
