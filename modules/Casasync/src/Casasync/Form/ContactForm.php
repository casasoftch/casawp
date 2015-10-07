<?php
namespace Casasync\Form;

use Zend\Form\Form;

class ContactForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();

    public function __construct(){
        parent::__construct('contact');

        $this->setAttribute('method', 'POST');
        $this->setAttribute('id', 'casasyncPropertyContactForm');
        //$this->setAttribute('action', '/immobilien/');

        $this->add(array(
            'name' => 'firstname',
            'type' => 'Text',
            'options' => array(
                'label' => __('First name', 'casasync'),
            ),
        ));

        $this->add(array(
            'name' => 'lastname',
            'type' => 'Text',
            'options' => array(
                'label' => __('Last name', 'casasync'),
            ),
        ));

        $this->add(array(
            'name' => 'street',
            'type' => 'Text',
            'options' => array(
                'label' => __('Street', 'casasync'),
            ),
        ));

        $this->add(array(
            'name' => 'postal_code',
            'type' => 'Text',
            'options' => array(
                'label' => __('ZIP', 'casasync'),
            ),
        ));


        $this->add(array(
            'name' => 'locality',
            'type' => 'Text',
            'options' => array(
                'label' => __('Locality', 'casasync'),
            ),
        ));

        $this->add(array(
            'name' => 'country',
            'type' => 'Select',
            'options' => array(
                'label' => __('Country', 'casasync'),
                'options' => array(
                    'CH' => 'Schweiz'
                )
            ),
        ));

        $this->add(array(
            'name' => 'phone',
            'type' => 'Text',
            'options' => array(
                'label' => __('Phone', 'casasync')
            ),
        ));

        $this->add(array(
            'name' => 'emailreal',
            'type' => 'Text',
            'options' => array(
                'label' => __('Email', 'casasync')
            ),
        ));

        $this->add(array(
            'name' => 'message',
            'type' => 'Textarea',
            'options' => array(
                'label' => __('Message', 'casasync')
            ),
            'attributes' => array(
                'rows' => 3
            )
        ));
    }

    public function getFilter(){
        $filter = new \Zend\InputFilter\InputFilter();
        $filter->add(array(
            'name' => 'firstname',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 2
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'lastname',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 2
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'street',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 2
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'postal_code',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 4
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'locality',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 2
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'phone',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'not_empty',
                ),
                array(
                    'name' => 'string_length',
                    'options' => array(
                        'min' => 2
                    ),
                ),
            ),
        ));
        $filter->add(array(
            'name' => 'emailreal',
            'required' => true,
            'validators' => array(
                array(
                    'name' => 'email_address',
                    'options' => array(
                        //'allow' => ALLOW_DNS,
                        'deep' => true,
                        'domain' => true,
                        'hostname' => '',
                        'mx' => true
                    )
                )
            ),
        ));

        

        return $filter;
    }
}