<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'CasasoftAuthService'   => 'CasasoftAuth\Service\AuthServiceFactory',
        )
    ),
    'view_helpers' => array(
        'factories' => array(
            'isAllowed' => 'CasasoftAuth\View\Helper\AuthAclAllowedFactory',
        ),
    ),
    'controllers' => array(
        'factories' => array(
            'CasasoftAuth\Controller\User' => 'CasasoftAuth\Controller\UserControllerFactory',
        )
    ),
    'view_manager' => array(
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
    /*'router' => array(
        'router_class' => 'Zend\Mvc\Router\Http\TranslatorAwareTreeRouteStack',
        'routes' => array(
            'user' => array(
                'type' => 'segment',
                'options' => array(
                    'route'    => '[/:lang]/{user}',
                    'defaults' => array(
                        'controller' => 'CasasoftAuth\Controller\User',
                        'action'     => 'profile',
                        'lang'       => 'de'
                    ),
                ),
                'may_terminate' => true,
                'child_routes' => array(
                    'login' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/{login}',
                            'defaults' => array(
                                'action'     => 'login'
                            ),
                        )
                    ),
                    'logout' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/{logout}',
                            'defaults' => array(
                                'action'     => 'logout'
                            ),
                        )
                    ),
                    'register' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/{register}',
                            'defaults' => array(
                                'action'     => 'register'
                            ),
                        )
                    ),
                    'confirm' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/{confirm}',
                            'defaults' => array(
                                'action'     => 'confirm-registration'
                            ),
                        )
                    ),
                    'forgot' => array(
                        'type' => 'segment',
                        'options' => array(
                            'route' => '/{forgot}',
                            'defaults' => array(
                                'action'     => 'forgot-password'
                            ),
                        )
                    ),
                ),
            ),

        ),
    ),*/
);
