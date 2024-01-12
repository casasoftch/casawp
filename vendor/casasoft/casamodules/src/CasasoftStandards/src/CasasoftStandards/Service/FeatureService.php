<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class FeatureService {

    public $items = [];
    public $translator;

    public function __construct($translator){
        $this->translator = $translator;
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
            'has-garage-underground' => array(
                'label' => $this->translator->translate('Garage underground', 'casasoft-standards'),
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


            'is-new-construction' => array(
                'label' => $this->translator->translate('New construction', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-new' => array(
                'label' => $this->translator->translate('As New', 'casasoft-standards'),
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
            'is-minergie-a' => array(
                'label' => $this->translator->translate('Minergie A', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-b' => array(
                'label' => $this->translator->translate('Minergie B', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-p' => array(
                'label' => $this->translator->translate('Minergie P', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-eco' => array(
                'label' => $this->translator->translate('Minergie-ECO', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-minergie-p-eco' => array(
                'label' => $this->translator->translate('Minergie-P-ECO', 'casasoft-standards'),
                'icon' => '',
            ),
           'is-non-smoking' => array(
               'label' => $this->translator->translate('Non smoking', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-partially-renovation-indigent' => array(
               'label' => $this->translator->translate('Partially renovation indigent', 'casasoft-standards'),
               'icon' => '',
           ),
           'is_projection' => array(
               'label' => $this->translator->translate('Projection', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-demolition-property' => array(
               'label' => $this->translator->translate('Demolition property', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-gutted' => array(
               'label' => $this->translator->translate('Gutted', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-shell-construction' => array(
               'label' => $this->translator->translate('Shell construction', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-well-tended' => array(
               'label' => $this->translator->translate('Well tended', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-modernized' => array(
               'label' => $this->translator->translate('Modernized', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-dilapidated' => array(
               'label' => $this->translator->translate('Dilapidated', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-renovation-indigent' => array(
               'label' => $this->translator->translate('Renovation indigent', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-refurbished' => array(
               'label' => $this->translator->translate('Refurbished', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-partially-refurbished' => array(
               'label' => $this->translator->translate('Partially refurbished', 'casasoft-standards'),
               'icon' => '',
           ),
           'is-first-time-occupancy' => array(
               'label' => $this->translator->translate('First time occupancy', 'casasoft-standards'),
               'icon' => '',
           ),
           'has-washing-machine' => array( // Waschmaschine
               'label' => $this->translator->translate('Washing machine', 'casasoft-standards'),
               'icon' => '',
           ),
           'has-tumbler' => array( // Tumbler
               'label' => $this->translator->translate('Tumbler', 'casasoft-standards'),
               'icon' => '',
           ),
           'has-kachelofen' => array( // Kachelofen
               'label' => $this->translator->translate('Kachelofen', 'casasoft-standards'),
               'icon' => '',
           ),
            'is-allowed-as-secondary-residency' => array(
                'label' => $this->translator->translate('Secondary residency', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-only-allowed-as-primary-residency' => array(
                'label' => $this->translator->translate('Primary residency', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-foreign-quota' => array(
                'label' => $this->translator->translate('Foreign quota', 'casasoft-standards'),
                'icon' => '',
            ),


            'is-attic' => array(
                'label' => $this->translator->translate('Attic', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-ground-floor' => array(
                'label' => $this->translator->translate('Ground floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-basement' => array(
                'label' => $this->translator->translate('Basement', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-garden-level' => array(
                'label' => $this->translator->translate('Garden level', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-raised-ground-floor' => array(
                'label' => $this->translator->translate('Raised ground floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-cellar-level' => array(
                'label' => $this->translator->translate('Cellar level', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-recessed-ground-floor' => array(
                'label' => $this->translator->translate('Recessed ground floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-ground-floor-and-first-floor' => array(
                'label' => $this->translator->translate('Ground floor and first floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-top-floor' => array(
                'label' => $this->translator->translate('Top floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'is-base-floor' => array(
                'label' => $this->translator->translate('Base floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-attic' => array(
                'label' => $this->translator->translate('Attic', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-charging-station' => array(
                'label' => $this->translator->translate('Charging station', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-dishwasher' => array(
                'label' => $this->translator->translate('Dishwasher', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-cellar' => array(
                'label' => $this->translator->translate('Cellar', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-playground' => array(
                'label' => $this->translator->translate('Playground', 'casasoft-standards'),
                'icon' => '',
            ),
            'has-remote-viewings' => array(
                'label' => $this->translator->translate('Remote viewing', 'casasoft-standards'),
                'icon' => '',
            ),

            'is-vat-opted' => [
                'label' => $this->translator->translate('VAT opted', 'casasoft-standards'),
                'icon' => '',
            ],

            'is-share-deal' => [
                'label' => $this->translator->translate('Share deal', 'casasoft-standards'),
                'icon' => '',
            ],

        );
    }

    public function setTranslator($translator) {
        $this->translator = $translator;
        $this->items = null;
    }

    public function addItem($obj, $key = null) {
        if ($key == null) {
            $this->items[] = $obj;
        } else {
            if (isset($this->items[$key])) {
                throw new KeyHasUseException("Key $key already in use.");
            }
            else {
                $this->items[$key] = $obj;
            }
        }
    }

    public function deleteItem($key) {
        if (isset($this->getItems()[$key])) {
            unset($this->getItems()[$key]);
        } else {
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItem($key) {
        if (isset($this->getItems()[$key])) {
            return $this->getItems()[$key];
        } else {
            return null;
            throw new \Exception("Invalid key $key.");
        }
    }

    public function getItems(){
        if (! $this->items) {
            //set default numvals
            $options = $this->getDefaultOptions();
            foreach ($options as $key => $options) {
                $feature = new Feature;
                $feature->populate($options);
                $feature->setKey($key);
                $this->addItem($feature, $key);
            }
        }
        return $this->items;
    }

    public function keys() {
        return array_keys($this->getItems());
    }

    public function length() {
        return count($this->getItems());
    }

    public function keyExists($key) {
        return isset($this->getItems()[$key]);
    }

}
