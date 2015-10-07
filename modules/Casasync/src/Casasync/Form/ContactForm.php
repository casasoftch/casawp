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
}