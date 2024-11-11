<?php
namespace CasasoftAuth\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;

use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\Factory as InputFactory;

use CasasoftAuth\Form\UserForgotPasswordForm;

use CasasoftAuth\Entity\User;

use Laminas\Cache\StorageFactory;
use Laminas\Session\SaveHandler\Cache;
use Laminas\Mvc\MvcEvent;

class UserController extends AbstractActionController {
    function __construct($em, $authService, $emailService, $translator){
        $this->em = $em;
        $this->authService = $authService;
        $this->emailService = $emailService;
        $this->translator = $translator;
        $this->sessionManager = new \Laminas\Session\SessionManager;
    }

    public function onDispatch(MvcEvent $e){
        $this->authService->auth();
        $this->user = $this->authService->getUser();
        $this->layout()->user = $this->user;
        $this->layout()->userMessages = $this->authService->getUnreadMessages();
        
        parent::onDispatch($e);
    }

    public function loginAction(){
        //$this->layout('layout/panel');

        $loginform = $this->authService->getLoginForm(
            $this->params()->fromQuery('target', $this->params()->fromPost('target', '')), //redirect url after success
            $this->url()->fromRoute('user/login', array(), true) //post to
        );
        $request = $this->getRequest();
        if ($request->isPost()) {
            $postdata = $request->getPost();
            $loginform->setData($request->getPost());
                 
            if ($loginform->isValid()) {
                $success = $this->authService->login($postdata['identity'], $postdata['credential'], $this->sessionManager->getId());
                if ($success) {
                    if ($loginform->get('target')->getValue()){
                        return $this->redirect()->toUrl(urldecode($loginform->get('target')->getValue()));
                    } else {
                        //return $this->redirect()->toRoute('admin', array('welcome' => 'true'), true);
                        //return $this->redirect()->toRoute('user/profile', array(), true);
                        return $this->redirect()->toRoute('home', array(), true);
                    }
                } else {
                    $loginform->get('credential')->setMessages(array('Benutzername oder/und Passwort konnten nicht verifiziert werden.'));
                }
            }
        }

    	$view = new ViewModel(array(
            'loginform' => $loginform
    	));
        return $view;
    }

    public function logoutAction(){
        $this->authService->logout();
        if ($this->params()->fromQuery('target')){
            return $this->redirect()->toUrl(urldecode($this->params()->fromQuery('target')));
        } else {
            return $this->redirect()->toRoute('user/login', array(), true);
        }
    }

    public function registerAction(){        

        $registrationform  = $this->authService->getRegistrationForm(
            $this->params()->fromQuery('target', '')
        );

        $request = $this->getRequest();

        if ($request->isPost()) {
            $postdata = $request->getPost();
            $registrationform->setData($postdata);

            if ($registrationform->isValid()) {
                $user = $this->authService->registerUser($postdata, $this->sessionManager->getId());
                if ($user) {
                    if ($registrationform->get('target')->getValue()){
                        $target_url = '?target=' . urlencode($registrationform->get('target')->getValue());
                        return $this->redirect()->toUrl($this->url()->fromRoute('user/confirm', array(), true) . $target_url);
                    } else {
                        return $this->redirect()->toRoute('user/confirm', array(), true);
                    }
                }
            } else{
            }
        }

        $view = new ViewModel(array(
            'registerform' => $registrationform
        ));
        return $view;
    }

    public function confirmRegistrationAction(){
        $reg = $this->params()->fromQuery('reg', false);
        $message = '';
        if ($reg) {
            $success = $this->authService->activateUser($reg, $this->sessionManager->getId());
            if ($success) {
                if ($this->params()->fromQuery('target')){
                    return $this->redirect()->toUrl(urldecode($this->params()->fromQuery('target')));
                } else {
                    return $this->redirect()->toRoute('user', array(), true);
                }
            } else {
                $message = 'failed';
            }
        } else {
            $newuser = $this->em->getRepository('CasasoftAuth\Entity\User')->findOneBy(array('lastSessionKey' => $this->sessionManager->getId()), array('id' => 'DESC'));
            if ($newuser && !$newuser->isVerified()) {
                $message = 'send';
                $this->emailService->sendEmail('confirm', 
                    array(
                        'to' => $newuser->getEmail(),
                        'subject' => "CasasoftAuth CRM: Bitte Registration verifizieren",
                        'user' => $newuser,
                        'confirm_url' => $this->url()->fromRoute('user/confirm', array(), true),
                        'target' => $this->params()->fromQuery('target')
                    )
                );
            } else {
                return $this->redirect()->toRoute('user', array(), true);
            }
        }
        $view = new ViewModel(array(
            'message' => $message
        ));
        return $view;
    }


    public function forgotPasswordAction(){

        $message = '';
        $message_type = 'error';
        $resetform  = new UserForgotPasswordForm();
        $resetform->setBindOnValidate(false);

        $request = $this->getRequest();
        if ($request->isPost()) {
            $postdata = $request->getPost();
            $resetform->setData($postdata);


                $req_user = false;
                if ($postdata['email']) {
                    $req_user = $this->em->getRepository('CasasoftAuth\Entity\User')->findOneBy(array('email' => $postdata['email']));                
                    if (!$req_user) {
                        $resetform->get('email')->setMessages(array('Keinen Benutzer unter dieser E-Mail Adresse gefunden.'));
                    }
                } else {
                    $resetform->get('email')->setMessages(array('Bitte E-Mail Adresse Angeben'));
                }

                $password_pass = true;
                if ($postdata['password1'] != $postdata['password2']) {
                    $resetform->get('password2')->setMessages(array('Die beiden Passwörter sind nicht identisch.'));
                    $password_pass = false;
                }
                if ($postdata['password1'] == '' && $postdata['password2'] == '') {
                    $resetform->get('password1')->setMessages(array('Bitte bestimmen Sie Ihr Passwort'));
                    $password_pass = false;
                }
                if (strlen($postdata['password1']) < 5) {
                    $resetform->get('password1')->setMessages(array('Das Passwort muss mindestens 5 charakteren besitzen.'));
                    $password_pass = false;
                }
            
                if ($req_user && $password_pass) {

                    $req_user->setReg($this->authService->encodeUserAuth($req_user->getUsername(), $postdata['password1']));
                    $this->em->persist($req_user);
                    $this->em->flush();

                    $this->emailService->sendEmail('reset', 
                        array(
                            'to' => $req_user->getEmail(),
                            'subject' => "Bitte Passwort-Änderung verifizieren",
                            'user' => $req_user,
                            'reset_url' => $this->url()->fromRoute('user/forgot', array(), true)
                        )
                    );

                    $message = "<i class='icon icon-ok-sign'></i> <strong>Erfolgreich!</strong> Ein E-Mail wurde an die angegebene Adresse versendet. Bitte klicken Sie auf den darin enthaltenen Link.";
                    $message_type = 'success';
                }
        }

        //return_link
        $masked_userauth = $this->params()->fromQuery('reg', false);
        if ($masked_userauth) {
            $user_r = $this->authService->decodeUserAuth($masked_userauth);
            $username = $user_r['username'];
            $password = $user_r['password'];

            $return_user = $this->em->getRepository('CasasoftAuth\Entity\User')->findOneBy(array('username' => $username));
            if ($return_user) {
                //set new password
                $return_user->setPasswordPlain($password);                    
                $this->em->persist($return_user);
                $this->em->flush();

                $message = '<strong>erfolgreich!</strong> Sie solten jetzt angemeldet sein.';
                $message_type = 'success';

                //remove reg
                $return_user->setReg(null);
                $this->em->persist($return_user);
                $this->em->flush();

                //log in user
                $result = $this->authService->login($username, $password);
                return $this->redirect()->toRoute('user', array(), true);
            }
        }

        $view = new ViewModel(array(
            'resetform' => $resetform,
            'message' => $message,
            'message_type' => $message_type
        ));
        return $view;
    }

    

}