<?php
namespace casawp\Form;

use Zend\Form\Form;

class FilterForm extends Form
{
    public $categories = array();
    public $salestypes = array();
    public $locations = array();
    public $availabilities = array();

    public function __construct($options, $categories = array(), $salestypes = array(), $locations = array(), $availabilities = array()){
        $this->categories = $categories;
        $this->salestypes = $salestypes;
        $this->locations = $locations;
        $this->availabilities = $availabilities;

        parent::__construct('filter');

        $this->setAttribute('method', 'GET');
        $this->setAttribute('action', '/immobilien/');


        if ($this->availabilities) {
            $this->add(array(
                'name' => 'availabilities',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Sales type', 'casawp'),
                    'value_options' => $this->getAvailabilityOptions(),
                ),
            ));
        }

        if ($this->salestypes) {
            $this->add(array(
                'name' => 'salestypes',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Sales type', 'casawp'),
                    'value_options' => $this->getSalestypeOptions(),
                ),
            ));
        }
        if ($this->categories) {
            if ($options['casawp_filter_categories_as_checkboxes']) {
                $this->add(array(
                    'name' => 'categories',
                    'type' => 'Zend\Form\Element\MultiCheckbox',
                    /*'attributes' => array(
                        'multiple' => 'multiple',
                    ),*/
                    'options' => array(
                        'label' => __('Category', 'casawp'),
                        'value_options' => $this->getCategoryOptions(),
                    ),
                ));
            } else {
                $this->add(array(
                    'name' => 'categories',
                    'type' => 'Select',
                    'attributes' => array(
                        'multiple' => 'multiple',
                    ),
                    'options' => array(
                        'label' => __('Category', 'casawp'),
                        'value_options' => $this->getCategoryOptions(),
                    ),
                ));
            }
        }
        if ($this->locations) {
            $location_options = $this->getLocationOptions();
            $this->add(array(
                'name' => 'locations',
                'type' => 'Select',
                'attributes' => array(
                    'multiple' => 'multiple',
                ),
                'options' => array(
                    'label' => __('Location', 'casawp'),
                    'value_options' => $location_options,
                ),
            ));
        }
    }

    public function getCategoryOptions(){
        //TODO SORTING!!!
        $category_options = array();
        foreach ($this->categories as $category) {
            $category_options[$category->getKey()] = html_entity_decode($category->getLabel());
        }
        asort($category_options);
        return $category_options;
    }

    public function getSalestypeOptions(){
        /*$salestype_options = array();
        foreach ($this->salestypes as $salestype) {
            $salestype_options[$salestype->getKey()] = $salestype->getLabel();
        }
        return $salestype_options;*/
        asort($this->salestypes);
        return $this->salestypes;
    }


    public function getAvailabilityOptions(){
        asort($this->availabilities);
        return $this->availabilities;
    }

    public function getLocationOptions(){
        $locations_workload = $this->locations;

        //not enough locations available
        if ($locations_workload <= 1) {
            return array();
        }

        $depth = 0;

        $parents = array();
        foreach ($locations_workload as $i => $location) {
            if ($location->parent == 0) {
                $parents[] = (array) $location;
                $depth = 1;
                unset($locations_workload[$i]);
            }
        }
        
        if ($depth == 1) {
            foreach ($parents as $u => $parent) {
                $children = array();
                foreach ($locations_workload as $i => $location) {
                    if ($location->parent == $parent['term_id']) {
                        $children[] = (array) $location;
                        $depth = 2;
                        unset($locations_workload[$i]);
                    }
                }
                $parents[$u]['children'] = $children;
            }
        }
        if ($depth == 2) {
            foreach ($parents as $u => $parent) {
                foreach ($parent['children'] as $c => $child) {
                    $grandchildren = array();
                    foreach ($locations_workload as $i => $location) {
                        if ($location->parent == $child['term_id']) {
                            $grandchildren[] = (array) $location;
                            $depth = 3;
                            unset($locations_workload[$i]);
                        }
                    }
                    $parents[$u]['children'][$c]['children'] = $grandchildren;
                }
            }
        }

        //ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //(again) ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //(again) (again) ignore parent if parent is alone
        if (count($parents) == 1 && $depth > 1) {
            $parents = $parents[0]['children'];
            $depth = $depth - 1;
        }

        //it there is only one parent ignore options
        if (count($parents) <= 1) {
            return array();
        }

        //build options array
        $options = array();

        if ($depth == 1) {
            foreach ($parents as $parent) {
                $options[$parent['slug']] = $parent['name'];
            } 
        } elseif ($depth == 2) {
            foreach ($parents as $parent) {
                $value_options = array();
                foreach ($parent['children'] as $child) {
                    $value_options[$child['slug']] = $child['name'];
                }
                $options[] = array(
                    'label' => $parent['name'],
                    'options' => $value_options
                );
            } 
        } elseif ($depth == 3){
            foreach ($parents as $parent) {
                foreach ($parent['children'] as $child) {
                    $label = $parent['name'] . ' ' . $child['name'];
                    $value_options = array();
                    foreach ($child['children'] as $grandchild) {
                        $value_options[$grandchild['slug']] = $grandchild['name'];
                    }
                    $options[] = array(
                        'label' => $label,
                        'options' => $value_options
                    );
                }
            } 
        }

        return $options;
    }
}