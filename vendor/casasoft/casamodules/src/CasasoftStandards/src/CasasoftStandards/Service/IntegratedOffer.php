<?php
namespace CasasoftStandards\Service;

use Laminas\Http\Request;
use Laminas\ServiceManager\FactoryInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class IntegratedOffer {
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
    
    private $cost;
    public function getCost(){return $this->cost;}
    public function setCost($cost){$this->cost = $cost;}

    private $timesegment = 'infinite';
    public function getTimesegment(){return $this->timesegment;}
    public function setTimesegment($timesegment){
        $allowed = array('infinite','m','d', 'w', 'y', 'h');
        if ($timesegment) {
            if (in_array($timesegment, $allowed)) {
                $this->timesegment = $timesegment;
            }
        } else {
            return false;
        }
    }

    private $propertysegment;
    public function getPropertysegment(){return $this->propertysegment;}
    public function setPropertysegment($propertysegment){$this->propertysegment = $propertysegment;}

    private $inclusive = false;
    public function getInclusive(){return $this->inclusive;}
    public function setInclusive($inclusive){$this->inclusive = $inclusive;}

    private $count;
    public function getCount(){return $this->count;}
    public function setCount($count){$this->count = $count;}

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
        if (isset($data['cost'])){
            $this->cost = $data['cost'];
        }
        if (isset($data['timesegment'])){
            $this->timesegment = $data['timesegment'];
        }
        if (isset($data['propertysegment'])){
            $this->propertysegment = $data['propertysegment'];
        }
        if (isset($data['inclusive'])){
            $this->inclusive = $data['inclusive'];
        }
        if (isset($data['count'])){
            $this->count = $data['count'];
        }
    }

    public function __toString() {
        return $this->label;
    }
}