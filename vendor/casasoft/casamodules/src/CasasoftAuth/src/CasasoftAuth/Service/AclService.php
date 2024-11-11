<?php
namespace CasasoftAuth\Service;

use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

use CasasoftAuth\Entity\User;


use Laminas\Permissions\Acl\Role\GenericRole as Role;
use Laminas\Permissions\Acl\Resource\GenericResource as Resource;

class AclService {
    public $config = array(
        'roles' => array(
            'registered' => array()
        )
    );
    protected $acl;

    public function __construct($acl){
        $this->acl = $acl;
    }

    public function setConfig($config){
        if (isset($congig['roles'])) {
            $roles = array_merge($this->config['roles'], $config['roles']);
        }
        
        $this->config = array_merge($this->config, $config);

        if (isset($congig['roles'])) {
            $this->config['roles'] = $roles;
        }

        $this->setRoles();
    }

    public function auth(){
        $result = $this->auth->getIdentity();
        if ($result instanceof User) {
            $this->user = $result;
            return true;
        } else {
            return false;    
        }
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function setRoles(){
        foreach ($this->config['roles'] as $role => $resources) {
            /*if (isset($resources['parents'])) {
                foreach ($resources['parents'] as $role) {
                    if (!$this->acl->hasRole($role)) {
                        $this->acl->addRole(new Role($role));
                    }
                }
            }*/
            $this->acl->addRole(new Role($role), (isset($resources['parents']) ? $resources['parents'] : null));
            
        }
        foreach ($this->config as $role => $resources) {
            if ($resources) {
                foreach ($resources as $resource) {
                    if (!is_array($resource)) {
                        if (!$this->acl->hasResource($resource)) {
                            $this->acl->addResource($resource);
                        }
                        $this->acl->allow($role, $resource);
                    }
                }
            }
        }
        return true;
    }

    public function isAllowed($role, $resource){
        
        if ($this->acl->hasResource($resource) && $this->acl->hasRole($role)) {
            return $this->acl->isAllowed($role, $resource);
        } else {
            return false;
        }
    }

    public function getAcl(){
        return $this->acl;
    }

}