<?php
namespace casawp\Form;

use Zend\Form\Form;

class ContactForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();
    public $customFilters = array();

    public function __construct(){
        parent::__construct('contact');

        $this->setAttribute('method', 'POST');
        $this->setAttribute('id', 'casawpPropertyContactForm');
        //$this->setAttribute('action', '/immobilien/');

        $this->add(array(
          'name' => 'form_id',
          'type' => 'hidden'
        ));

        $this->add(array(
            'name' => 'gender',
            'type' => 'radio',
            'options' => array(
              'label' => __('Anrede', 'casawp'),
              'options' => array(
                '2' => 'Frau',
                '1' => 'Herr'
              )
            ),
            'attributes' => array(
              'value' => '2'
            )
        ));

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
                    'IT' => 'Italien',
                    'other' => 'Sonstige'
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
            'name' => 'mobile',
            'type' => 'Text',
            'options' => array(
                'label' => __('Mobile', 'casawp')
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

    public function setCustomFilters($filters){
        $this->customFilters = $filters;
    }

    private function isInCustomFilters($field){
        foreach ($this->customFilters as $filter) {
            if ($filter['name'] == $field) {
                return true;
            }
        }
        return false;
    }

    public function getFilter(){
        $filter = new \Zend\InputFilter\InputFilter();
        if (!$this->isInCustomFilters('firstname')) {
            $filter->add(array(
                'name' => 'firstname',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('lastname')) {
            $filter->add(array(
                'name' => 'lastname',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('street')) {
            $filter->add(array(
                'name' => 'street',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('postal_code')) {
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
        }
        if (!$this->isInCustomFilters('locality')) {
            $filter->add(array(
                'name' => 'locality',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('locality')) {
            $filter->add(array(
                'name' => 'phone',
                'required' => true,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('mobile')) {
            $filter->add(array(
                'name' => 'mobile',
                'required' => false,
                'validators' => array(
                    array(
                        'name' => 'not_empty',
                    ),
                    /*array(
                        'name' => 'string_length',
                        'options' => array(
                            'min' => 2
                        ),
                    ),*/
                ),
            ));
        }
        if (!$this->isInCustomFilters('emailreal')) {
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
        }
        if (!$this->isInCustomFilters('gender')) {
            $filter->add(array(
                'name' => 'gender',
                'required' => false
            ));
        }

        foreach ($this->customFilters as $custom_filter_array) {
            $filter->add($custom_filter_array);
        }



        return $filter;
    }
}

