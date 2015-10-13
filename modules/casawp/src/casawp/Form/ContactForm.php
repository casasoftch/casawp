<?php
namespace casawp\Form;

use Zend\Form\Form;

class ContactForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();

    public function __construct(){
        parent::__construct('contact');

        $this->setAttribute('method', 'POST');
        $this->setAttribute('id', 'casawpPropertyContactForm');
        //$this->setAttribute('action', '/immobilien/');

        $this->add(array(
            'name' => 'firstname',
            'type' => 'Text',
            'options' => array(
                'label' => __('First name', 'casawp'),
            ),
        ));

        $this->add(array(
            'name' => 'lastname',
            'type' => 'Text',
            'options' => array(
                'label' => __('Last name', 'casawp'),
            ),
        ));

        $this->add(array(
            'name' => 'street',
            'type' => 'Text',
            'options' => array(
                'label' => __('Street', 'casawp'),
            ),
        ));

        $this->add(array(
            'name' => 'postal_code',
            'type' => 'Text',
            'options' => array(
                'label' => __('ZIP', 'casawp'),
            ),
        ));


        $this->add(array(
            'name' => 'locality',
            'type' => 'Text',
            'options' => array(
                'label' => __('Locality', 'casawp'),
            ),
        ));

        $this->add(array(
            'name' => 'country',
            'type' => 'Select',
            'options' => array(
                'label' => __('Country', 'casawp'),
                'options' => array(
                    'CH' => 'Schweiz',
                    'AT' => 'Östereich',
                    'DE' => 'Deutschland',
                    'FR' => 'Frankreich',
                    'IT' => 'Italien'
                )
            ),
        ));

        $this->add(array(
            'name' => 'phone',
            'type' => 'Text',
            'options' => array(
                'label' => __('Phone', 'casawp')
            ),
        ));

        $this->add(array(
            'name' => 'emailreal',
            'type' => 'Text',
            'options' => array(
                'label' => __('Email', 'casawp')
            ),
        ));

        $this->add(array(
            'name' => 'message',
            'type' => 'Textarea',
            'options' => array(
                'label' => __('Message', 'casawp')
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