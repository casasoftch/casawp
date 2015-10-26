<?php
namespace CasasoftAuth\Form;

use CasasoftAuth\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Zend\Form\Fieldset;
use Zend\InputFilter\InputFilterProviderInterface;

class UserFieldset extends Fieldset implements InputFilterProviderInterface
{
    private $em;
    private $filter = array(
        'email' => array(
            'required' => true,
            'validators' => array(
            ),
        ),
        'name' => array(
            'required' => true,
        ),
        'username' => array(
            'required' => true,
        ),
        'contact_id' => array(
            'required' => false
        ),
        'role' => array(
            'required' => false
        )
    );

    public function __construct($em = false, $lang = 'de'){
        $this->em = $em;

        // we want to ignore the name passed
        parent::__construct('user');
        $this->setAttribute('method', 'post');
        $this->add(array(
            'type' => 'Zend\Form\Element\Hidden',
            'name' => 'id',
        ));

         $this->setHydrator(new DoctrineHydrator($em))
             ->setObject(new User());
        $this->add(array(
            'name' => 'email',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('Email')
            ),
        ));

        $this->add(array(
            'name' => 'name',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('Full name')
            ),
        ));

        $this->add(array(
            'name' => 'username',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('Username')
            ),
        ));

        $this->add(array(
            'name' => 'postalcode',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('ZIP')
            ),
        ));

        $this->add(array(
            'name' => 'locality',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('Locality')
            ),
        ));

        $this->add(array(
            'name' => 'mobilephone',
            'type'       => 'Zend\Form\Element\Text',
            'options' => array(
                'label' => _('Mobile number')
            ),
        ));


        $this->add(array(
             'type' => 'Zend\Form\Element\Select',
             'name' => 'locale',
             'options' => array(
                'label' => _('Language'),
                'value_options' => array(
                    'de' => \Locale::getDisplayLanguage('de', 'de'),
                    'en' => \Locale::getDisplayLanguage('en', 'en'),
                    'fr' => \Locale::getDisplayLanguage('fr', 'fr'),
                    'it' => \Locale::getDisplayLanguage('it', 'it'),
                ),
             )
        ));

        $this->add(array(
             'type' => 'Zend\Form\Element\Select',
             'name' => 'role',
             'options' => array(
                'label' => _('Role'),
                'value_options' => array(
                    'registered' => 'Minimal',
                    'client' => 'Klient',
                    'marketing' => 'Marketing',
                    'receptionist' => 'Rezeption',
                    'salesSupport' => 'Verkaufs-Unterstützung',
                    'consultant' => 'Verkauf',
                    'editor' => 'Editor',
                    'admin' => 'Administrator',
                ),
             )
        ));


      /*  $addressFieldset = new AddressFieldset($em, $lang);
        $this->add(array(
            'type'    => 'Zend\Form\Element\Collection',
            'name'    => 'addresses',
            'options' => array(
                'count'           => 1, 
                'target_element'  => $addressFieldset,
                //'should_create_template' => true
            )
        ));*/

    }

    public function overrideInputFilterSpecification($field, $options){
        $this->filter[$field] = $options;
    }

    public function getInputFilterSpecification(){
        return $this->filter;
    }
}