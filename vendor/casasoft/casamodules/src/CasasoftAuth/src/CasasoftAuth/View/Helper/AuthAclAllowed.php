<?php
namespace CasasoftAuth\View\Helper;

use Zend\View\Helper\AbstractHelper;  

class AuthAclAllowed extends AbstractHelper{ 

    public function __construct($authService){
        $this->authService = $authService;
    }

    public function __invoke($role){
        return $this->authService->isAllowed($role);
    }
}