<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class UtilityService{

    public $items = array();

    public function __construct($translator){
        $this->translator = $translator;

        //set default utilitys
        $utility_options = $this->getDefaultOptions();
        foreach ($utility_options as $key => $options) {
            $utility = new Utility;
            $utility->populate($options);
            $utility->setKey($key);
            $this->addItem($utility, $key);
        }
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getDefaultOptions(){
        return array(
            'commercial' => array(
                'label' => $this->translator->translate('Commercial', 'casasoft-standards'),
                'icon' => '',
            ),
            'gastronomy' => array(
                'label' => $this->translator->translate('Gastronomy', 'casasoft-standards'),
                'icon' => '',
            ),
            'vacation' => array(
                'label' => $this->translator->translate('Vacation', 'casasoft-standards'),
                'icon' => '',
            ),
            'agricultural' => array(
                'label' => $this->translator->translate('Agricultural', 'casasoft-standards'),
                'icon' => '',
            ),
            'industrial' => array(
                'label' => $this->translator->translate('Industrial', 'casasoft-standards'),
                'icon' => '',
            ),
            'residential' => array(
                'label' => $this->translator->translate('Residential', 'casasoft-standards'),
                'icon' => '',
            ),
            'storage' => array(
                'label' => $this->translator->translate('Storage', 'casasoft-standards'),
                'icon' => '',
            ),
            'parking' => array(
                'label' => $this->translator->translate('Parking', 'casasoft-standards'),
                'icon' => '',
            ),
            'building' => array(
                'label' => $this->translator->translate('Building', 'casasoft-standards'),
                'icon' => '',
            ),
            'investment' => array(
                'label' => $this->translator->translate('Investment', 'casasoft-standards'),
                'icon' => '',
            ),
        );
    }

    public function addItem($obj, $key = null) {
        if ($key == null) {
            $this->items[] = $obj;
        } else {
            if (isset($this->items[$key])) {
                throw new KeyHasUseException("Key $key already in use.");
            } else {
                $this->items[$key] = $obj;
            }
        }
    }

    public function deleteItem($key) {
        if (isset($this->items[$key])) {
            unset($this->items[$key]);
        } else {
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItem($key) {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        } else {
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItems(){
        return $this->items;
    }

    public function keys() {
        return array_keys($this->items);
    }

    public function length() {
        return count($this->items);
    }

    public function keyExists($key) {
        return isset($this->items[$key]);
    }

}
