<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class CategoryService implements FactoryInterface {

    public $items = array();

    public function __construct($translator){
        $this->translator = $translator;

        //set default categorys
        $category_options = $this->getDefaultOptions();
        foreach ($category_options as $key => $options) {
            $category = new Category;
            $category->populate($options);
            $category->setKey($key);
            $this->addItem($category, $key);
        }
    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getDefaultOptions(){
        return array(
            'apartment' => array(
                'label' => $this->translator->translate('Apartment', 'casasoft-standards'),
                'icon' => '',
            ),
            'attic-flat' => array(
                'label' => $this->translator->translate('Attic flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'bachelor-flat' => array(
                'label' => $this->translator->translate('Bachelor flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'bifamiliar-house' => array(
                'label' => $this->translator->translate('Bifamiliar house', 'casasoft-standards'),
                'icon' => '',
            ),
            'building-land' => array(
                'label' => $this->translator->translate('Building land', 'casasoft-standards'),
                'icon' => '',
            ),
            'double-garage' => array(
                'label' => $this->translator->translate('Double garage', 'casasoft-standards'),
                'icon' => '',
            ),
            'duplex' => array(
                'label' => $this->translator->translate('Duplex', 'casasoft-standards'),
                'icon' => '',
            ),
            'factory' => array(
                'label' => $this->translator->translate('Factory', 'casasoft-standards'),
                'icon' => '',
            ),
            'farm' => array(
                'label' => $this->translator->translate('Farm', 'casasoft-standards'),
                'icon' => '',
            ),
            'farm-house' => array(
                'label' => $this->translator->translate('Farm house', 'casasoft-standards'),
                'icon' => '',
            ),
            'furnished-flat' => array(
                'label' => $this->translator->translate('Furnished flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'garage' => array(
                'label' => $this->translator->translate('Garage', 'casasoft-standards'),
                'icon' => '',
            ),
            'house' => array(
                'label' => $this->translator->translate('House', 'casasoft-standards'),
                'icon' => '',
            ),
            'loft' => array(
                'label' => $this->translator->translate('Loft', 'casasoft-standards'),
                'icon' => '',
            ),
            'mountain-farm' => array(
                'label' => $this->translator->translate('Mountain farm', 'casasoft-standards'),
                'icon' => '',
            ),
            'multiple-dwelling' => array(
                'label' => $this->translator->translate('Multiple dwelling', 'casasoft-standards'),
                'icon' => '',
            ),
            'open-slot' => array(
                'label' => $this->translator->translate('Open slot', 'casasoft-standards'),
                'icon' => '',
            ),
            'parking-space' => array(
                'label' => $this->translator->translate('Parking space', 'casasoft-standards'),
                'icon' => '',
            ),
            'plot' => array(
                'label' => $this->translator->translate('Plot', 'casasoft-standards'),
                'icon' => '',
            ),
            'roof-flat' => array(
                'label' => $this->translator->translate('Roof flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'row-house' => array(
                'label' => $this->translator->translate('Row house', 'casasoft-standards'),
                'icon' => '',
            ),
            'single-garage' => array(
                'label' => $this->translator->translate('Single garage', 'casasoft-standards'),
                'icon' => '',
            ),
            'single-house' => array(
                'label' => $this->translator->translate('Single house', 'casasoft-standards'),
                'icon' => '',
            ),
            'single-room' => array(
                'label' => $this->translator->translate('Single room', 'casasoft-standards'),
                'icon' => '',
            ),
            'terrace-flat' => array(
                'label' => $this->translator->translate('Terrace flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'terrace-house' => array(
                'label' => $this->translator->translate('Terrace house', 'casasoft-standards'),
                'icon' => '',
            ),
            'underground-slot' => array(
                'label' => $this->translator->translate('Underground slot', 'casasoft-standards'),
                'icon' => '',
            ),
            'villa' => array(
                'label' => $this->translator->translate('Villa', 'casasoft-standards'),
                'icon' => '',
            ),
            'chalet' => array(
                'label' => $this->translator->translate('Chalet', 'casasoft-standards'),
                'icon' => '',
            ),
            'studio' => array(
                'label' => $this->translator->translate('Studio', 'casasoft-standards'),
                'icon' => '',
            ),
            'house' => array(
                'label' => $this->translator->translate('House', 'casasoft-standards'),
                'icon' => '',
            ),
            'covered-slot' => array(
                'label' => $this->translator->translate('Covered slot', 'casasoft-standards'),
                'icon' => '',
            ),


            //new
            'building-project' => array(
                'label' => $this->translator->translate('Construction project', 'casasoft-standards'),
                'icon' => '',
            ),


            //new new
            'flat' => array(
                'label' => $this->translator->translate('Flat', 'casasoft-standards'),
                'icon' => '',
            ),
            'ground-floor-flat' => array(
                'label' => $this->translator->translate('Ground floor', 'casasoft-standards'),
                'icon' => '',
            ),
            'office' => array(
                'label' => $this->translator->translate('Office', 'casasoft-standards'),
                'icon' => '',
            ),
            'exhibition-space' => array(
                'label' => $this->translator->translate('Exihition space', 'casasoft-standards'),
                'icon' => '',
            ),
            'retail' => array(
                'label' => $this->translator->translate('Retail', 'casasoft-standards'),
                'icon' => '',
            ),
            'bed-and-breakfast' => array(
                'label' => $this->translator->translate('Bed and breakfast', 'casasoft-standards'),
                'icon' => '',
            ),
            'hotel' => array(
                'label' => $this->translator->translate('Hotel', 'casasoft-standards'),
                'icon' => '',
            ),
            'warehouse' => array(
                'label' => $this->translator->translate('Warehouse', 'casasoft-standards'),
                'icon' => '',
            ),
            'workshop' => array(
                'label' => $this->translator->translate('Workshop', 'casasoft-standards'),
                'icon' => '',
            ),
            'car-park' => array(
                'label' => $this->translator->translate('Car park', 'casasoft-standards'),
                'icon' => '',
            ),
            'bungalow' => array(
                'label' => $this->translator->translate('Bungalow', 'casasoft-standards'),
                'icon' => '',
            ),
            'castle' => array(
                'label' => $this->translator->translate('Castle', 'casasoft-standards'),
                'icon' => '',
            ),
            'fuel-station' => array(
                'label' => $this->translator->translate('Fuel station', 'casasoft-standards'),
                'icon' => '',
            ),
            'cafe-bar' => array(
                'label' => $this->translator->translate('Café / Bar', 'casasoft-standards'),
                'icon' => '',
            ),

            'maisonette' => array(
                'label' => $this->translator->translate('Maisonette', 'casasoft-standards'),
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
        } else {
            throw new \Exception("Invalid key $key.");
        }
    }

    // public function findItem($slug){
    //     foreach ($this->items as $item) {
    //         if ($item->getKey() == $slug) {
    //             return $item;
    //         }
    //     }
    //     return false;
    // }

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