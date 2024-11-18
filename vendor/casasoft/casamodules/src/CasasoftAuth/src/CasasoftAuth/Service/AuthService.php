<?php
namespace CasasoftAuth\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

use CasasoftAuth\Entity\User;

use CasasoftAuth\Form\UserLoginForm;
use CasasoftAuth\Form\UserRegisterForm;
use CasasoftAuth\Form\UserForgotPasswordForm;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\Factory as InputFactory;

use Laminas\Permissions\Acl\Acl;
use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;
use DoctrineModule\Stdlib\Hydrator\DoctrineObject as DoctrineHydrator;


use Laminas\Http\Header\SetCookie;

class AuthService {
    protected $user;
    protected $auth;
    protected $em;
    protected $storage;
    protected $request;
    protected $config = array();

    public function __construct($authAdapter, $em, $storage, $aclService, $request, $session, $blamableListener, $plugins){
        $this->auth = $authAdapter;
        $this->em = $em;
        $this->storage = $storage;
        $this->aclService = $aclService;
        $this->hydrator = new DoctrineHydrator($this->em);
        $this->request = $request;
        $this->session = $session;
        $this->blamableListener = $blamableListener;
        $this->plugins = $plugins;
    }

    public function setConfig($config){
        $this->config = array_merge($this->config, $config);
    }

    public function auth(){
        $result = $this->auth->getIdentity();
        if ($result instanceof User) {
            $this->user = $result;
            //$this->blamableListener->setUserValue($this->user);
            switch ($this->user->getLocale()) {
                case 'de': setlocale(LC_TIME, array('de_CH.utf8', 'de_CH')); break;
                case 'en': setlocale(LC_TIME, array('en_US.utf8', 'en_US')); break;
                case 'fr': setlocale(LC_TIME, array('fr_CH.utf8', 'fr_CH')); break;
                case 'it': setlocale(LC_TIME, array('it_CH.utf8', 'it_CH')); break;                
                default: setlocale(LC_TIME, array('de_CH.utf8', 'de_CH')); break;
            }
            return true;
        } else {
            return false;    
        }
    }

    public function getSession(){return $this->session;}
    public function setSession($session){$this->session = $session;}

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    //deligation to aclService
    public function isAllowed($resource){

        if (!$this->aclService) {
            return false;
        }
        if ($this->getUser()) {
            return $this->aclService->isAllowed($this->getUser()->getRole(), $resource);
        } else {
            return false;
        }
    }

    public function getAcl(){
        if (!$this->aclService) {
            throw new \Exception("Please define a acl service for the auth service", 1);
        }
        return $this->aclService->getAcl();
    }

    public function getUser(){
        return $this->user;
    }

    public function base64url_encode($data) { 
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '='); 
    } 

    public function base64url_decode($data) { 
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT)); 
    } 

    public function encodeUserAuth($username, $password){
        //userauth = {1}{2}{3}
        // {1} represents string length of username 003 014
        // {2} represents username
        // {3} represents password
        $userauth = sprintf('%03d', strlen($username)) . $username . $password;

        $masked_userauth = $this->base64url_encode($userauth);
        return $masked_userauth;
    }

    public function decodeUserAuth($masked_userauth){
        $userauth = $this->base64url_decode($masked_userauth);
        $usernamelength = substr($userauth, 0, 3);
        $username = substr($userauth, 3, $usernamelength); 
        $password = substr($userauth, $usernamelength+3); 

        return array(
            'username' => $username,
            'password' => $password
        );
    }

    public function encodeSessionID($sessionID){
        $this->salt = $this->config['salt'];
        $masked_string = $this->salt . strrev($sessionID);
        return $masked_string;
    }

    public function decodeSessionID($masked_string){
        $this->salt = $this->config['salt'];
        $sessionID = strrev( substr($masked_string, strlen($this->salt) ) );
        return $sessionID;
    }

    public function logout(){
        if ($this->getUser()) {
            $this->forgetMe($this->getUser());
        }
        
        $this->auth->getAdapter()->setIdentityValue(false);
        $this->auth->getAdapter()->setCredentialValue(false);
        $result = $this->auth->authenticate(); 
        $this->user = false;

        return true;
    }

    public function getLoginForm($target = '', $action = false){
        $loginform = new UserLoginForm;
        $loginform->setBindOnValidate(false);

        if ($action) {
            $loginform->setAttribute('action', $action);
        }
       
        if ($target) {
            $loginform->get('target')->setValue($target);
        }

        $inputFilter = new InputFilter();
        $factory     = new InputFactory();
        $inputFilter->add($factory->createInput(array(
            'name'     => 'identity',
            'required' => true
        )));
        $inputFilter->add($factory->createInput(array(
            'name'     => 'credential',
            'required' => true
        )));
        $loginform->setInputFilter($inputFilter);
        return $loginform;
    }

    public function getRegistrationForm($target = '', $action = false){
        $registrationform  = new UserRegisterForm($this->em);
        $registrationform->setBindOnValidate(false);
        
        if ($action) {
            $registrationform->setAttribute('action', $action);
        }

        if ($target) {
            $registrationform->get('target')->setValue($target);
        }

        $user = new User;
        $registrationform->bind($user);
        
        //$inputFilter = $user->getInputFilter();
       // $inputFilter = new InputFilter;

        //$factory     = new InputFactory();
      /*  $inputFilter->add(array(
            'name'     => 'email',
            'required' => true,
            'validators' => array(
                array(
                    'name'      => 'DoctrineModule\Validator\NoObjectExists',
                    'options' => array(
                        'object_repository' => $this->em->getRepository('CasasoftAuth\Entity\User'),
                        'fields'            => 'email',
                        'messages' => array(
                            \DoctrineModule\Validator\NoObjectExists::ERROR_OBJECT_FOUND => 'Diese E-Mail-Adresse wird bereits verwendet.',
                        ),
                    ),
                ),
            ),
        ));*/

       /* $inputFilter->add($factory->createInput(array(
            'name'     => 'password2',
            'required' => true,
            'validators' => array(
                array(
                    'name'      => 'Laminas\Validator\Identical',
                    'options' => array(
                        'token' => 'password1',
                    ),
                ),
            ),
        )));*/

       // $registrationform->setInputFilter($inputFilter);

        return $registrationform;
    }

    public function registerUser($postdata, $session = null){
        if ($postdata instanceof \Laminas\Stdlib\Parameters) {
            $postdata = $postdata->toArray();
        }

        if (!isset($postdata['password1'])) {
            $postdata['password1'] = md5(time());
        }
        $plain_password = $postdata['password1'];

        $user = new User;
        $this->hydrator->hydrate($postdata['user'], $user);
        //$user->populate($postdata);
        
        //$user->create_time = time();
    
        $user->setLastSessionKey($session);
        $user->setPasswordPlain($plain_password);
        $user->setRole('registered');
        
        $user->setReg($this->encodeUserAuth($user->getEmail(), $plain_password));
        
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    public function activateUser($reg, $session){
        $user = $this->em->getRepository('CasasoftAuth\Entity\User')->findOneBy(array('reg' => $reg));
        if ($user) {
            $user->setVerified(1);
            $user->setReg(null);
            $user->setLastSessionKey($session);
            $this->em->persist($user);
            $this->em->flush();

            $this->storage->write($user);
            return true;
        }
        return false;
    }

    //creates rememberMe tokens for system and client
    public function rememberMe($user){

        $this->session->rememberMe();

    }

    //remove rememberMe tokens from system and client
    public function forgetMe($user = false){
        $this->session->forgetMe();
/*
        if ($user) {
            $user->setRmLookupKey(null);
            $user->setRmPrivateKey(null);
        }
        
        $this->request->getHeaders()->get('Cookie')->email = null;
        $this->request->getHeaders()->get('Cookie')->lookup = null;
        $this->request->getHeaders()->get('Cookie')->token = null;*/
    }

    //checks if a user has been remembered (cookie) and returns credentials
    public function getRememberance(){
        $cookie = $this->request->getHeaders()->get('Cookie');
        if (
            $cookie->offsetExists('email')
            && $cookie->offsetExists('lookup')
            && $cookie->offsetExists('token')
        ) {
            $cookieEmail = $cookie->email;
            $cookieLookup = $cookie->lookup;
            $cookieToken = $cookie->token;
            
            //1. get user based on lookup and email
                $user = $this->em->findOneBy(array('rmLookupKey' => $cookieLookup, 'email' => $cookieEmail));
            //2. check if token is correct
                if ($user && hash_hmac('md5', $cookieLookup, $user->getRmPrivateKey()) == $cookieToken) {
                    return $user; 
                } else {
                    $this->forgetMe();
                }

        }

        return false;
    }

    public function login($username, $password, $session = '', $remember_me = true){
        $this->auth->getAdapter()->setIdentityValue($username);
        $this->auth->getAdapter()->setCredentialValue($password);

        $result = $this->auth->authenticate();

        if (!$result) {
            //try as email
        }
        
        
        if (is_object($result->getIdentity()) && property_exists($result->getIdentity(), 'verified') && $result->getIdentity()->isVerified() === true) {
            $this->user = $result->getIdentity();

            //$this->user->date_last_login = new \DateTime();
            $this->user->setLast_login(new \DateTime());
            //$this->user->setLastSessionKey(new \DateTime());
            
            $this->em->persist($this->user);
            if ($remember_me) {
                //$this->rememberMe($this->user);
            }
            $this->em->flush();

             //set new last_session
          /*  $sessionManager = new SessionManager();
            $this->user->last_session_key = $sessionManager->getId();
            $this->em->persist($this->user);
            $this->em->flush();
            $this->flashMessenger()->setNamespace('success');
            $this->flashMessenger()->addMessage($this->translator->translate('You are now logged in.'));
            if (isset($postdata['target']) && $postdata['target']){
                return $this->redirect()->toUrl(urldecode($postdata['target']));
            } else {
                return $this->redirect()->toRoute('user', array(), true);
            }*/

           return true;
        } else {
            /*//remember me?
            $user = $this->getRememberance();
            if ($user) {
                $this->user = $user;

                //reset tokens
                $this->rememberMe($this->user);

                //set new infos
                $this->user->date_last_login = new \DateTime();
                $this->em->persist($this->user);
                $this->em->flush();
                return true;
            }
            */


            $this->logout();
            return false;
        }
    }

    //returns user to login page
    public function kick(){
        return $this->plugins->get('redirect')->toUrl($this->plugins->get('url')->fromRoute('user/login') . '?target='.urlencode($this->request->getUri()));
    }

    public function getIdentity(){
        return $this->auth->getIdentity();
    }

    public function hasIdentity(){
        if ($this->getUser()) {
            return true;
        }
        return false;
    }

    public function getUnreadMessages(){
        /*if ($this->auth->getIdentity() && $this->auth->getIdentity()->getContact()) {
            return $this->em->getRepository('CasasoftAuth\Entity\ContactMessage')->findBy(
                array(
                    'to' => $this->auth->getIdentity()->getContact(),
                    'read' => 0
                ),
                array(),
                5,
                0
            );
        }*/
        return false;        
    }


}