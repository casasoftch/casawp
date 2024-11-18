<?php
namespace CasasoftAuth\Form;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;


class UserProfileForm extends Form
{
    public function __construct(ObjectManager $objectManager, $lang = 'de'){
        parent::__construct('profile');

        $this->setHydrator(new DoctrineHydrator($objectManager));

        $userFieldset = new UserFieldset($objectManager, $lang);
        $userFieldset->setUseAsBaseFieldset(true);
        $userFieldset->overrideInputFilterSpecification('email', array('required' => false));
        $userFieldset->overrideInputFilterSpecification('username', array('required' => false));
        $this->add($userFieldset);

        $this->add(array(
            'name' => 'target',
            'attributes' => array(
                'type'  => 'hidden'
            )
        ));

    }
}