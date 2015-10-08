<?php
namespace CasasoftAuth\Form;

use Zend\Form\Form;
use Zend\Form\Element;

class UserLoginForm extends Form
{
    public function __construct($name = null)
    {
        // we want to ignore the name passed
        parent::__construct('event');
        $this->setAttribute('method', 'post');
        $this->add(array(
            'name' => 'id',
            'type' => 'hidden',
        ));
        $this->add(array(
            'name' => 'submit',
            'type' => 'hidden',
        ));



        $this->add(array(
            'name' => 'target',
            'type' => 'hidden',
        ));
        $this->add(array(
            'name' => 'identity',
            'type' => 'text',
            'options' => array(
                'label' => _('Username')
            ),
            'attributes' => array(
                'placeholder' => _('Username'),
                'class' => 'form-control'
            )
        ));
        $this->add(array(
            'name' => 'credential',
            'type' => 'password',
            'options' => array(
                'label' => _('Password'),
            ),
            'attributes' => array(
                'placeholder' => _('Password'),
                'class' => 'form-control'
            )
        ));
        
        $this->add(array(
            'name'       => 'submit',
            'type'       => 'Zend\Form\Element\Submit',
            'attributes' => array(
                'class' => 'btn-primary'
            ),
            'options'    => array(
               'primary'    => true,
               'label' => _('Login'),
               'glyphicon' => array(
                    'icon' => 'arrow-right',
                    'position' => 'append'
                )
            ),
        ));

    }
}