<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class Numval {
    private $key;
    public function getKey(){return $this->key;}
    public function setKey($key){$this->key = $key;}

    private $icon;
    public function getIcon(){return $this->icon;}
    public function setIcon($icon){$this->icon = $icon;}

    private $label;
    public function getLabel(){return $this->label;}
    public function setLabel($label){$this->label = $label;}

    private $type;
    public function getType(){return $this->type;}
    public function setType($type){$this->type = $type;}

    private $si;
    public function getSi(){return $this->si;}
    public function setSi($si){$this->si = $si;}

     //for temporary storage
    private $value;
    public function getValue(){return $this->value;}
    public function setValue($value){$this->value = $value;}
    
    private $translator;
    public function __construct(){
    }
    public function populate($data){
        if (isset($data['key'])) {
            $this->key = $data['key'];
        } 
        if (isset($data['icon'])){
            $this->icon = $data['icon'];
        }
        if (isset($data['label'])){
            $this->label = $data['label'];
        }
        if (isset($data['type'])){
            $this->type = $data['type'];
        }
        if (isset($data['si'])){
            $this->si = $data['si'];
        }
    }

    public function __toString() {
        return $this->label;
    }
}