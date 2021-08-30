<?php
namespace CasasoftStandards\Service;

use Zend\Http\Request;
use Zend\ServiceManager\FactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

use Doctrine\ORM\Tools\Pagination\Paginator;

class HeatService {

    public $items = [];
    private $groups = null;

    public function __construct($translator){
        $this->translator = $translator;

    }

    public function createService(ServiceLocatorInterface $serviceLocator){
        return $this;
    }

    public function getDefaultOptions(){

        return [
            'electric' => [
                'label' => $this->translator->translate('Electric heating', 'casasoft-standards')
            ],
            'geothermal-probe' => [
                'label' => $this->translator->translate('Geothermal-probe heating', 'casasoft-standards')
            ],
            'district' => [
                'label' => $this->translator->translate('District heating', 'casasoft-standards')
            ],
            'gas' => [
                'label' => $this->translator->translate('Gas heating', 'casasoft-standards')
            ],
            'wood' => [
                'label' => $this->translator->translate('Wood heating', 'casasoft-standards')
            ],
            'air-water-heatpump' => [
                'label' => $this->translator->translate('Air-water-heatpump heating', 'casasoft-standards')
            ],
            'oil' => [
                'label' => $this->translator->translate('Oil heating', 'casasoft-standards')
            ],
            'pellet' => [
                'label' => $this->translator->translate('Pellet heating', 'casasoft-standards')
            ],
            'heatpump' => [
                'label' => $this->translator->translate('Heatpump heating', 'casasoft-standards')
            ],
            'floor' => [
                'label' => $this->translator->translate('Floor heating', 'casasoft-standards')
            ],
            'radiators' => [
                'label' => $this->translator->translate('Radiators', 'casasoft-standards')
            ],
            'radiators' => [
                'label' => $this->translator->translate('Radiators', 'casasoft-standards')
            ],
            'infrared' => [
                'label' => $this->translator->translate('Infrared', 'casasoft-standards')
            ],
            'wall' => [
                'label' => $this->translator->translate('Wall heating', 'casasoft-standards')
            ],
            'solar-thermal' => [
                'label' => $this->translator->translate('Solar Thermal', 'casasoft-standards')
            ],
            'photovoltaics' => [
                'label' => $this->translator->translate('Photovoltaics', 'casasoft-standards')
            ],
            'coal' => [
                'label' => $this->translator->translate('Coal', 'casasoft-standards')
            ],
            'heatpump-brine-and-water' => [
                'label' => $this->translator->translate('Brine-water heat pump', 'casasoft-standards')
            ],
            'bhkw' => [
                'label' => $this->translator->translate('Block-type thermal power station', 'casasoft-standards')
            ],
        ];
    }

    public function getDefaultGroupOptions(){
        $groups = [
            'heatGeneration' => [
                'label' => $this->translator->translate('Heat generation', 'casasoft-standards'),
                'heat_slugs' => [
                    'electric',
                    'geothermal-probe',
                    'district',
                    'gas',
                    'wood',
                    'air-water-heatpump',
                    'oil',
                    'pellet',
                    'heatpump',
                    'solar-thermal',
                    'photovoltaics',
                    'coal',
                    'heatpump-brine-and-water',
                    'bhkw'
                ],
            ],
            'heatDistribution' => [
                'label' => $this->translator->translate('Heat distribution', 'casasoft-standards'),
                'heat_slugs' => [
                    'floor',
                    'radiators',
                    'infrared',
                    'wall'
                ],
            ],
        ];

        return $groups;
    }

    public function setTranslator($translator) {
        $this->translator = $translator;
        $this->items = null;
        $this->groups = null;
    }


    public function hasSlugInGroup($slug, $groupslug){
        if (array_key_exists($groupslug, $this->getGroups())) {
            if (in_array($slug, $this->getGroups()[$groupslug]['heat_slugs'])) {
                return true;
            }
        }
        return false;
    }

    public function hasASlugInGroup($slugs, $groupslug){
        foreach ($slugs as $slug) {
            if ($this->hasSlugInGroup($slug, $groupslug)) {
                return true;
            }
        }
        return false;
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

    public function getGroup($key) {
        if (isset($this->getGroups()[$key])) {
            return $this->getGroups()[$key];
        } else {
            return false;
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
            //set default categorys
            $category_options = $this->getDefaultOptions();
            foreach ($category_options as $key => $options) {
                $category = new Category;
                $category->populate($options);
                $category->setKey($key);
                $this->addItem($category, $key);
            }
        }
        return $this->items;
    }

    public function getGroups() {
        if (! $this->groups) {
            $this->groups = $this->getDefaultGroupOptions();
        }
        return $this->groups;
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
