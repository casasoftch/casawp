<?php
namespace CasasoftAuth\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;

class UserForgotPasswordForm extends Form
{
    public function __construct()
    {
        // we want to ignore the name passed
        parent::__construct('forgot-password');
        $this->setAttribute('method', 'post');

        //personal
        $this->add(array(
            'name' => 'email',
            'attributes' => array(
                'type'  => 'email'
            ),
            'options' => array(
                'label' => 'E-Mail Adresse'
            ),
        ));
        $this->add(array(
            'name' => 'password1',
            'attributes' => array(
                'type'  => 'password'
            ),
            'options' => array(
                'label' => 'Gewünschtes Passwort'
            ),
        ));
        $this->add(array(
            'name' => 'password2',
            'attributes' => array(
                'type'  => 'password'
            ),
            'options' => array(
                'label' => 'Passwort wiederholen'
            ),
        ));
        

        $this->add(array(
            'name'       => 'submit',
            'type'       => 'Laminas\Form\Element\Submit',
            'attributes' => array(
                'class' => 'btn-primary'
            ),
            'options'    => array(
               'primary'    => true,
               'label' => 'Zurücksetzen',
               'glyphicon' => array(
                    'icon' => 'arrow-right',
                    'position' => 'append'
                )
            ),
        ));

    }
}