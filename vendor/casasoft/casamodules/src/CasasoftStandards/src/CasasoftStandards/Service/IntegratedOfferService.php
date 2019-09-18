<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class IntegratedOfferService {

    public $items = [];
    private $template;

    public function __construct($translator){
        $this->translator = $translator;
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getTemplate(){
        return $this->template;
    }
    public function setTemplate($template){
        $this->template = $template;
    }

    public function getDefaultOptions(){
        return [
            'parking-exterior-space' => [
                'label' => $this->translator->translate('External parking space', 'casasoft-standards'), //Aussenparkplatz
                'icon' => '',
            ],
            'parking-carport' => [
                'label' => $this->translator->translate('Carport', 'casasoft-standards'), //Carport
                'icon' => '',
            ],
            'parking-garage-connected' => [
                'label' => $this->translator->translate('Connected garage', 'casasoft-standards'),
                'icon' => '',
            ],
            'parking-garage-box' => [
                'label' => $this->translator->translate('Garage box', 'casasoft-standards'), //Garagenbox
                'icon' => '',
            ],
            'parking-duplex' => [
                'label' => $this->translator->translate('Duplex garage', 'casasoft-standards'), //Duplex
                'icon' => '',
            ],
            'parking-garage-underground' => [
                'label' => $this->translator->translate('Underground parking garage', 'casasoft-standards'), //Tiefgaragenparkplatz
                'icon' => '',
            ],
            'parking-garage' => [
                'label' => $this->translator->translate('Single garage', 'casasoft-standards'),
                'icon' => '',
            ],
            'parking-house' => [
                'label' => $this->translator->translate('Parking structure', 'casasoft-standards'), //Parkhaus
                'icon' => '',
            ],
            'parking-double-garage' => [
                'label' => $this->translator->translate('Double garage', 'casasoft-standards'), //Parkhaus
                'icon' => '',
            ],
            'room-workroom' => [
                'label' => $this->translator->translate('Workroom', 'casasoft-standards'),
                'icon' => '',
            ],
            'room-storage-basement' => [
                'label' => $this->translator->translate('Storage basement', 'casasoft-standards'),
                'icon' => '',
            ]
        ];
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
            } else {
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
            return false;
        }
    }

    public function getItems(){
        if (! $this->items) {
            //set default integrated offers
            $options = $this->getDefaultOptions();
            foreach ($options as $key => $options) {
                $integrated_offer = new IntegratedOffer;
                $integrated_offer->populate($options);
                $integrated_offer->setKey($key);
                $this->addItem($integrated_offer, $key);
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
