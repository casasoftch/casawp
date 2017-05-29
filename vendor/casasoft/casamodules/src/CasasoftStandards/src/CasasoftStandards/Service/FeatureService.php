<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class FeatureService implements FactoryInterface {

    public $items = array();

    public function __construct($translator){
        $this->translator = $translator;

        //set default numvals
        $options = $this->getDefaultOptions();
        foreach ($options as $key => $options) {
            $feature = new Feature;
            $feature->populate($options);
            $feature->setKey($key);
            $this->addItem($feature, $key);
        }

    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getDefaultOptions(){

        return array(
            //disable?
            'has-fireplace' => array(
                'label' => $this->translator->translate('Chimney fireplace', 'casasoft-standards'),
                'icon' => 'glyphicon glyphicon-fire'
            ),
            'has-cabletv' => array(
                'label' => $this->translator->translate('Cable TV', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-ramp' => array(
                'label' => $this->translator->translate('Ramp', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-lifting-platform' => array(
                'label' => $this->translator->translate('Has lifting platform', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-cable-railway-terminal' => array(
                'label' => $this->translator->translate('Cable railway terminal', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-train-station' => array(
                'label' => $this->translator->translate('Train station', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-railway-terminal' => array(
                'label' => $this->translator->translate('Train station', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-flat-sharing-community' => array(
                'label' => $this->translator->translate('Flat sharing community', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-connected-building-land' => array(
                'label' => $this->translator->translate('Connected building land', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-gardenhouse' => array(
                'label' => $this->translator->translate('Gardenhouse', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-under-roof' => array(
                'label' => $this->translator->translate('Is under roof', 'casasoft-standards'),
                'icon' => '',
            ),


            'has-rental-deposit-guarantee' => array(
                'label' => $this->translator->translate('Rental deposit guarantee', 'casasoft-standards'),
                'icon' => '',
            ),

            'has-raised-ground-floor' => array(
                'label' => $this->translator->translate('Raised ground floor', 'casasoft-standards'),
                'icon' => '',
            ),
            
            'has-elevator' => array(
                'label' => $this->translator->translate('Has elevator', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-nice-view' => array(
                'label' => $this->translator->translate('Nice view', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'has-lake-view' => array(
                'label' => $this->translator->translate('Lake view', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'has-mountain-view' => array(
                'label' => $this->translator->translate('Mountain view', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'is-sunny' => array(
                'label' => $this->translator->translate('Sunny', 'casasoft-standards'),
                'icon' => '',
            ), 


            //alpha
            'is-quiet' => array(
                'label' => $this->translator->translate('Quiet', 'casasoft-standards'),
                'icon' => '',
            ), 

            //alpha
            'is_projection' => array(
                'label' => $this->translator->translate('Projected construction (not yet realized)', 'casasoft-standards'),
                'icon' => '',
            ), 

            //alpha
            'has_demolition_property' => array(
                'label' => $this->translator->translate('Contains demolition structure', 'casasoft-standards'),
                'icon' => '',
            ), 


            
            'is-child-friendly' => array(
                'label' => $this->translator->translate('Child friendly', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-parking' => array(
                'label' => $this->translator->translate('Parking', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-garage' => array(
                'label' => $this->translator->translate('Garage', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'has-double-garage' => array(
                'label' => $this->translator->translate('Double garage', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'has-car-port' => array(
                'label' => $this->translator->translate('Car port', 'casasoft-standards'),
                'icon' => '',
            ),

            //alpha
            'has-double-car-port' => array(
                'label' => $this->translator->translate('Double car port', 'casasoft-standards'),
                'icon' => '',
            ),


            //alpha
            'on-a-slope' => array(
                'label' => $this->translator->translate('On a slope', 'casasoft-standards'),
                'icon' => '',
            ),
            //alpha
            'on-a-south-slope' => array(
                'label' => $this->translator->translate('South-facing slope', 'casasoft-standards'),
                'icon' => '',
            ),
            //alpha
            'on-even-ground' => array(
                'label' => $this->translator->translate('Even Ground', 'casasoft-standards'),
                'icon' => '',
            ),

            'has-balcony' => array(
                'label' => $this->translator->translate('Balcony', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-isdn' => array(
                'label' => $this->translator->translate('ISDN', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-wheelchair-accessible' => array(
                'label' => $this->translator->translate('Wheelchair accessible', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-animal-friendly' => array(
                'label' => $this->translator->translate('Animal friendly', 'casasoft-standards'),
                'icon' => '',
            ),
            
            
            
            'has-restrooms' => array(
                'label' => $this->translator->translate('Restrooms', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-water-supply' => array(
                'label' => $this->translator->translate('Water supply', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-sewage-supply' => array(
                'label' => $this->translator->translate('Sewage supply', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-power-supply' => array(
                'label' => $this->translator->translate('Power supply', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-gas-supply' => array(
                'label' => $this->translator->translate('Gas supply', 'casasoft-standards'),
                'icon' => '',
            ),
            
            'is-corner-house' => array(
                'label' => $this->translator->translate('Corner house', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-middle-house' => array(
                'label' => $this->translator->translate('Middle house', 'casasoft-standards'),
                'icon' => '',
            ),
            
            
            
            'is-new' => array(
                'label' => $this->translator->translate('New', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-old' => array(
                'label' => $this->translator->translate('Old', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-building-law-restrictions' => array(
                'label' => $this->translator->translate('has building law restrictions', 'casasoft-standards'),
                'icon' => '',
            ),
            
            'has-swimmingpool' => array(
                'label' => $this->translator->translate('Swimmingpool', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-general' => array(
                'label' => $this->translator->translate('Minergie general', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-certified' => array(
                'label' => $this->translator->translate('Minergie certified', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-non-smoking' => array(
                'label' => $this->translator->translate('Is non smoking', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-partially-renovation-indigent' => array(
                'label' => $this->translator->translate('Is partially renovation indigent', 'casasoft-standards'),
                'icon' => '',
            ),
            'is_projection' => array(
                'label' => $this->translator->translate('Is projection', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-demolition-property' => array(
                'label' => $this->translator->translate('Is demolition property', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-gutted' => array(
                'label' => $this->translator->translate('Is gutted', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-shell-construction' => array(
                'label' => $this->translator->translate('Is shell construction', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-well-tended' => array(
                'label' => $this->translator->translate('Is well tended', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-modernized' => array(
                'label' => $this->translator->translate('Is modernized', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-dilapidated' => array(
                'label' => $this->translator->translate('Is dilapidated', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-renovation-indigent' => array(
                'label' => $this->translator->translate('Is renovation indigent', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-refurbished' => array(
                'label' => $this->translator->translate('Is refurbished', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-partially-refurbished' => array(
                'label' => $this->translator->translate('Is partially refurbished', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-new' => array(
                'label' => $this->translator->translate('Is new', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-first-time-occupancy' => array(
                'label' => $this->translator->translate('Is first time occupancy', 'casasoft-standards'),
                'icon' => '',
            ),

        );
    }

    public function addItem($obj, $key = null) {
        if ($key == null) {
            $this->items[] = $obj;
        }
        else {
            if (isset($this->items[$key])) {
                throw new KeyHasUseException("Key $key already in use.");
            }
            else {
                $this->items[$key] = $obj;
            }
        }
    }

    public function deleteItem($key) {
        if (isset($this->items[$key])) {
            unset($this->items[$key]);
        }
        else {            
            throw new \Exception("Invalid key $key.");
        }
    }
     
    public function getItem($key) {
        if (isset($this->items[$key])) {
            return $this->items[$key];
        }
        else {
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