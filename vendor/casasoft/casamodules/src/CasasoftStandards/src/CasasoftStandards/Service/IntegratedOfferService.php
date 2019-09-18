<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class IntegratedOfferService implements FactoryInterface {

    public $items = array();
    private $template;

    public function __construct($translator){
        $this->translator = $translator;

        //set default integrated offers
        $options = $this->getDefaultOptions();
        foreach ($options as $key => $options) {
            $integrated_offer = new IntegratedOffer;
            $integrated_offer->populate($options);
            $integrated_offer->setKey($key);
            $this->addItem($integrated_offer, $key);
        }
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
        return array(
            'parking-exterior-space' => array(
                'label' => $this->translator->translate('External parking space', 'casasoft-standards'), //Aussenparkplatz
                'icon' => '',
            ),
            'parking-carport' => array(
                'label' => $this->translator->translate('Carport', 'casasoft-standards'), //Carport
                'icon' => '',
            ),
            'parking-garage-connected' => array(
                'label' => $this->translator->translate('Connected garage', 'casasoft-standards'),
                'icon' => '',
            ),
            'parking-garage-box' => array(
                'label' => $this->translator->translate('Garage box', 'casasoft-standards'), //Garagenbox
                'icon' => '',
            ),
            'parking-duplex' => array(
                'label' => $this->translator->translate('Duplex garage', 'casasoft-standards'), //Duplex
                'icon' => '',
            ),
            'parking-garage-underground' => array(
                'label' => $this->translator->translate('Underground parking garage', 'casasoft-standards'), //Tiefgaragenparkplatz
                'icon' => '',
            ),
            'parking-garage' => array(
                'label' => $this->translator->translate('Single garage', 'casasoft-standards'),
                'icon' => '',
            ),
            'parking-house' => array(
                'label' => $this->translator->translate('Parking structure', 'casasoft-standards'), //Parkhaus
                'icon' => '',
            ),
            'room-workroom' => array(
                'label' => $this->translator->translate('Workroom', 'casasoft-standards'),
                'icon' => '',
            ),
            'room-storage-basement' => array(
                'label' => $this->translator->translate('Storage basement', 'casasoft-standards'),
                'icon' => '',
            )
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