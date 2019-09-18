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

    private function matchGeakLetter($value) {
        switch ($value) {
            case 1:
                return 'A';
                break;
            case 2:
                return 'B';
                break;
            case 3:
                return 'C';
                break;
            case 4:
                return 'D';
                break;
            case 5:
                return 'E';
                break;
            case 6:
                return 'F';
                break;
            case 7:
                return 'G';
                break;
            default:
                # code...
                break;
        }
    }

    public function getRenderedValue(Array $options = []){
        $options = array_merge([
            'km_convert_at' => 501
        ], $options);

        $number_filter = new \Zend\I18n\Filter\NumberFormat("de_CH");

        $val = $this->getValue();
        $km = false;

        //km conversion
        if (in_array($this->getSi(), ['m'])) {
            if ($options['km_convert_at'] && $val >= $options['km_convert_at']) {
                $val = $val / 1000;
                $km = true;
            }
        }
        switch ($this->getSi()) {
            case 'm':
                $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm');
                break;
            case 'm2':
                $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm') . '<sup>2</sup>';
                break;
            case 'm3':
                $val = $number_filter->filter($val) . ' ' . ($km ? 'km' : 'm') . '<sup>3</sup>';
                break;
            case 'kg':
                $val = $number_filter->filter($val) . ' kg';
                break;
            case '%':
                $val = $number_filter->filter($val) . ' %';
                break;
            case 'currency':
                $val = ($options['currency']) . ' ' . number_format($val, 0, '.', "'") . '.–';
                break;
            case 'geak':
                $val = $this->matchGeakLetter($val);
                break;
        }

        return $val;
    }

    private $translator;
    public function __construct(){
    }
    public function populate($data){
        if (isset($data['key'])) {
            $this->key = $data['key'];
        }
        if (isset($data['icon'])) {
            $this->icon = $data['icon'];
        }
        if (isset($data['label'])) {
            $this->label = $data['label'];
        }
        if (isset($data['type'])) {
            $this->type = $data['type'];
        }
        if (isset($data['si'])) {
            $this->si = $data['si'];
        }
    }

    public function __toString() {
        return $this->label;
    }
}
