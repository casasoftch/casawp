<?php
namespace CasasoftAuth\Form;

use Doctrine\Common\Persistence\ObjectManager;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;


class UserRegisterForm extends Form implements InputFilterProviderInterface
{
    private $em = false;
    public function __construct(ObjectManager $objectManager, $lang = 'de'){
        $this->em = $objectManager;


        $this->em = $objectManager;
        parent::__construct('register');

        $this->setHydrator(new DoctrineHydrator($objectManager));

        $userFieldset = new UserFieldset($objectManager, $lang);
        $userFieldset->setUseAsBaseFieldset(true);
        $this->add($userFieldset);

        $this->add(array(
            'name' => 'target',
            'attributes' => array(
                'type'  => 'hidden'
            )
        ));

        $this->add(array(
            'name' => 'password1',
            'attributes' => array(
                'type'  => 'password'
            ),
            'options' => array(
                'label' => _('Chosen Password')
            ),
        ));
     /*   $this->add(array(
            'name' => 'password2',
            'attributes' => array(
                'type'  => 'password'
            ),
            'options' => array(
                'label' => _('Repeat Password')
            ),
        ));*/

        /*$this->setValidationGroup(array(
            'csrf',
            'user' => array(
                'email',
                'firstname',
                'lastname',
                'address' => array(
                    'street'
                )
            )
        ));*/

    }

    function getInputFilterSpecification(){
        return array(
         
            'password1' => array(
                'required' => true,
            ),
            /*'password2' => array(
                'required' => true,
                'validators' => array(
                    array(
                        'name'      => 'Laminas\Validator\Identical',
                        'options' => array(
                            'token' => 'password1',
                        ),
                    ),
                ),
            )*/
        );
    }

    public function getInputFilter(){
        $formInputFilter = parent::getInputFilter();

        //email should not exists (register)
        $emailInput = $formInputFilter->get('user')->get('email');
        $emailUnique = new \DoctrineModule\Validator\NoObjectExists(array(
            'object_manager' => $this->em,
            'object_repository' => $this->em->getRepository('CasasoftAuth\Entity\User'),
            'fields' => 'email'
        ));
        $emailUnique->setMessage('Diese E-Mail-Adresse wird bereits verwendet.', 'objectFound');
        $emailInput->getValidatorChain()->attach($emailUnique);


        $usernameInput = $formInputFilter->get('user')->get('username');
        $usernameUnique = new \DoctrineModule\Validator\NoObjectExists(array(
            'object_manager' => $this->em,
            'object_repository' => $this->em->getRepository('CasasoftAuth\Entity\User'),
            'fields' => 'username'
        ));
        $usernameUnique->setMessage('Dieser Benutzername wird bereits verwendet.', 'objectFound');
        $usernameInput->getValidatorChain()->attach($usernameUnique);

        return $formInputFilter;
    }



}