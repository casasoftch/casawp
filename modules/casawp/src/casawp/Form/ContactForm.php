<?php
namespace casawp\Form;

use Zend\Form\Form;
use casawp\Conversion;

class ContactForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();
    public $customFilters = array();

    public function __construct(){
        parent::__construct('contact');

        $converter = new Conversion;

        $this->setAttribute('method', 'POST');
        $this->setAttribute('id', 'casawpPropertyContactForm');
        //$this->setAttribute('action', '/immobilien/');

        $this->add(array(
          'name' => 'form_id',
          'type' => 'hidden'
        ));

        $genderArray = array(
            '2' => __('Ms.', 'casawp'),
            '1' => __('Mr.', 'casawp')
        );

        if (get_option('casawp_form_gender_neutral', false)) {
            $genderArray = array(
                '2' => __('Ms.', 'casawp'),
                '1' => __('Mr.', 'casawp'),
                '0' => '*'
            );
        }

        $this->add(array(
            'name' => 'gender',
            'type' => 'radio',
            'options' => array(
              'label' => __('Anrede', 'casawp'),
              'options' => $genderArray
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
            'attributes' => array(
                'required' => get_option('casawp_form_firstname_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'lastname',
            'type' => 'Text',
            'options' => array(
                'label' => __('Last name', 'casawp'),
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_lastname_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'legal_name',
            'type' => 'Text',
            'options' => array(
                'label' => __('Legal name', 'casawp'),
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_legal_name_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'street',
            'type' => 'Text',
            'options' => array(
                'label' => __('Street', 'casawp'),
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_street_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'postal_code',
            'type' => 'Text',
            'options' => array(
                'label' => __('ZIP', 'casawp'),
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_postal_code_required', false),
            ),
        ));


        $this->add(array(
            'name' => 'locality',
            'type' => 'Text',
            'options' => array(
                'label' => __('Locality', 'casawp'),
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_locality_required', false),
            ),
        ));


        $isos = array('AE', 'AL', 'AD', 'AT', 'BY', 'BE', 'BA', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FO', 'FI', 'FR', 'DE', 'GI', 'GR', 'HU', 'IS', 'IE', 'IM', 'IT', 'RS', 'LV', 'LI', 'LT', 'LU', 'MK', 'MT', 'MD', 'MC', 'ME', 'NL', 'NO', 'PL', 'PT', 'RO', 'RU', 'SM', 'RS', 'SK', 'SI', 'ES', 'SE', 'CH', 'UA', 'GB', 'VA', 'RS');
        $countries = array();
        foreach ($isos as $iso) {
          $countries[$iso] = $converter->countrycode_to_countryname($iso);
        }
        //setlocale(LC_ALL, 'fr_FR');
        //sort($countries, SORT_LOCALE_STRING);


        $country_sort_tmp = $countries;
        foreach ($country_sort_tmp as $index => $country) {
          if (in_array(mb_substr($country, 0, 1), array('Î'))) {
            $country_sort_tmp[$index] = 'I'.$country;
          }
        }
        asort($country_sort_tmp);

        $countries = array_merge($country_sort_tmp, $countries);

        $countries = array('CH' => $converter->countrycode_to_countryname('CH')) + $countries; //beginning
        $countries['other'] = __('Other', 'casawp'); //end

        $this->add(array(
            'name' => 'country',
            'type' => 'Select',
            'value' => 'CH',
            'options' => array(
                'label' => __('Country', 'casawp'),
                'options' => $countries
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_country_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'phone',
            'type' => 'Text',
            'options' => array(
                'label' => __('Phone', 'casawp')
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_phone_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'mobile',
            'type' => 'Text',
            'options' => array(
                'label' => __('Mobile', 'casawp')
            ),
            'attributes' => array(
                'required' => get_option('casawp_form_mobile_required', false),
            ),
        ));

        $this->add(array(
            'name' => 'emailreal',
            'type' => 'Text',
            'options' => array(
                'label' => __('Email', 'casawp')
            ),
            'attributes' => array(
                'required' => true
            )
        ));

        $this->add(array(
            'name' => 'message',
            'type' => 'Textarea',
            'options' => array(
                'label' => __('Message', 'casawp')
            ),
            'attributes' => array(
                'rows' => 3,
                'required' => get_option('casawp_form_message_required', false),
            )
        ));
    }

    public function escapeJavaScriptText($string){
        return str_replace("\n", '\n', str_replace('"', '\"', addcslashes(str_replace("\r", '', (string)$string), "\0..\37'\\")));
    }

    public function setCustomFilters($filters){
        $this->customFilters = $filters;
    }

    private function isInCustomFilters($field){
        foreach ($this->customFilters as $filter) {
            if (is_array($filter) && $filter['name'] == $field) {
                return true;
            } else if (is_object($filter) && $filter->getName() == $field) {
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
                'required' => get_option('casawp_form_firstname_required', true),
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
                'required' => get_option('casawp_form_lastname_required', true),
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
        if (!$this->isInCustomFilters('legal_name')) {
            $filter->add(array(
                'name' => 'legal_name',
                'required' => get_option('casawp_form_legal_name_required', false)
            ));
        }
        if (!$this->isInCustomFilters('street')) {
            $filter->add(array(
                'name' => 'street',
                'required' => get_option('casawp_form_street_required', true),
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
                'required' => get_option('casawp_form_postalcode_required', true),
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
                'required' => get_option('casawp_form_locality_required', true),
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
        if (!$this->isInCustomFilters('phone')) {
            $filter->add(array(
                'name' => 'phone',
                'required' => get_option('casawp_form_phone_required', true),
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
                'required' => get_option('casawp_form_mobile_required', false),
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
        if (!$this->isInCustomFilters('message')) {
            $filter->add(array(
                'name' => 'message',
                'required' => get_option('casawp_form_message_required', false),
                'validators' => array(),
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
